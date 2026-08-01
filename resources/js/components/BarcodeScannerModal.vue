<script setup lang="ts">
import {
    Camera,
    Flashlight,
    ImagePlus,
    RefreshCw,
    X,
    ZoomIn,
} from '@lucide/vue';
import type { Html5Qrcode as Html5QrcodeInstance } from 'html5-qrcode';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const emit = defineEmits<{
    detected: [value: string];
    close: [];
}>();

const readerId = 'product-barcode-camera-reader';
const errorMessage = ref('');
const controlMessage = ref('');
const isStarting = ref(false);
const supportsTorch = ref(false);
const torchEnabled = ref(false);
const supportsZoom = ref(false);
const zoomMin = ref(1);
const zoomMax = ref(1);
const zoomStep = ref(0.1);
const zoomValue = ref(1);
let scanner: Html5QrcodeInstance | null = null;
let detectionLocked = false;

type ExtendedTrackCapabilities = MediaTrackCapabilities & {
    focusMode?: string[];
};

type ExtendedTrackConstraintSet = MediaTrackConstraintSet & {
    focusMode?: ConstrainDOMString;
};

function resetCameraControls(): void {
    supportsTorch.value = false;
    torchEnabled.value = false;
    supportsZoom.value = false;
    zoomMin.value = 1;
    zoomMax.value = 1;
    zoomStep.value = 0.1;
    zoomValue.value = 1;
}

async function stopScanner(): Promise<void> {
    const currentScanner = scanner;
    scanner = null;

    if (!currentScanner) {
        return;
    }

    try {
        if (currentScanner.isScanning) {
            await currentScanner.stop();
        }

        currentScanner.clear();
    } catch {
        // Camera may already be released by the browser.
    }
}

async function createScanner(): Promise<Html5QrcodeInstance> {
    const { Html5Qrcode, Html5QrcodeSupportedFormats } =
        await import('html5-qrcode');

    return new Html5Qrcode(readerId, {
        formatsToSupport: [
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.EAN_8,
            Html5QrcodeSupportedFormats.UPC_A,
            Html5QrcodeSupportedFormats.UPC_E,
            Html5QrcodeSupportedFormats.UPC_EAN_EXTENSION,
            Html5QrcodeSupportedFormats.CODE_39,
            Html5QrcodeSupportedFormats.CODE_93,
            Html5QrcodeSupportedFormats.CODE_128,
            Html5QrcodeSupportedFormats.ITF,
            Html5QrcodeSupportedFormats.CODABAR,
            Html5QrcodeSupportedFormats.RSS_14,
            Html5QrcodeSupportedFormats.RSS_EXPANDED,
            Html5QrcodeSupportedFormats.QR_CODE,
        ],
        experimentalFeatures: {
            useBarCodeDetectorIfSupported: true,
        },
        verbose: false,
    });
}

async function configureCameraFeatures(): Promise<void> {
    if (!scanner?.isScanning) {
        return;
    }

    const trackCapabilities =
        scanner.getRunningTrackCapabilities() as ExtendedTrackCapabilities;

    if (trackCapabilities.focusMode?.includes('continuous')) {
        try {
            const focusConstraint: ExtendedTrackConstraintSet = {
                focusMode: 'continuous',
            };
            await scanner.applyVideoConstraints({
                advanced: [focusConstraint],
            });
        } catch {
            // Some browsers report focus support but reject manual constraints.
        }
    }

    try {
        const cameraCapabilities = scanner.getRunningTrackCameraCapabilities();
        const torchFeature = cameraCapabilities.torchFeature();
        supportsTorch.value = torchFeature.isSupported();

        const zoomFeature = cameraCapabilities.zoomFeature();
        supportsZoom.value = zoomFeature.isSupported();

        if (supportsZoom.value) {
            zoomMin.value = zoomFeature.min();
            zoomMax.value = zoomFeature.max();
            zoomStep.value = zoomFeature.step() || 0.1;
            zoomValue.value = zoomFeature.value() ?? zoomMin.value;

            const preferredZoom = Math.min(
                zoomMax.value,
                Math.max(zoomMin.value, zoomMin.value + zoomStep.value * 2),
            );

            try {
                await zoomFeature.apply(preferredZoom);
                zoomValue.value = preferredZoom;
            } catch {
                supportsZoom.value = false;
            }
        }
    } catch {
        resetCameraControls();
    }
}

async function handleDetection(value: string): Promise<void> {
    const normalizedValue = value.trim();

    if (!normalizedValue || detectionLocked) {
        return;
    }

    detectionLocked = true;
    navigator.vibrate?.(80);
    emit('detected', normalizedValue);
    await stopScanner();
}

async function startScanner(): Promise<void> {
    errorMessage.value = '';
    controlMessage.value = '';
    isStarting.value = true;
    detectionLocked = false;
    resetCameraControls();
    await stopScanner();
    await nextTick();

    if (!window.isSecureContext) {
        errorMessage.value =
            'Kamera hanya dapat digunakan melalui HTTPS atau localhost.';
        isStarting.value = false;

        return;
    }

    try {
        scanner = await createScanner();

        await scanner.start(
            {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1920, min: 640 },
                height: { ideal: 1080, min: 480 },
            },
            {
                fps: 18,
                qrbox: (viewfinderWidth, viewfinderHeight) => ({
                    width: Math.floor(viewfinderWidth * 0.92),
                    height: Math.floor(Math.min(190, viewfinderHeight * 0.42)),
                }),
                aspectRatio: 1.7778,
                disableFlip: false,
            },
            handleDetection,
            () => undefined,
        );

        await configureCameraFeatures();
    } catch (error) {
        const technicalMessage =
            error instanceof Error
                ? `${error.name} ${error.message}`
                : String(error);
        errorMessage.value = /NotAllowed|Permission|denied/i.test(
            technicalMessage,
        )
            ? 'Izin kamera ditolak. Aktifkan izin kamera pada browser lalu coba kembali.'
            : 'Kamera tidak dapat dibuka. Pastikan kamera tersedia dan tidak sedang digunakan aplikasi lain.';
    } finally {
        isStarting.value = false;
    }
}

async function toggleTorch(): Promise<void> {
    if (!scanner?.isScanning || !supportsTorch.value) {
        return;
    }

    const torchFeature = scanner
        .getRunningTrackCameraCapabilities()
        .torchFeature();
    const nextValue = !torchEnabled.value;

    try {
        await torchFeature.apply(nextValue);
        torchEnabled.value = nextValue;
    } catch {
        controlMessage.value =
            'Lampu kamera tidak dapat diaktifkan pada perangkat ini.';
    }
}

async function updateZoom(event: Event): Promise<void> {
    if (!scanner?.isScanning || !supportsZoom.value) {
        return;
    }

    const value = Number((event.target as HTMLInputElement).value);

    try {
        await scanner
            .getRunningTrackCameraCapabilities()
            .zoomFeature()
            .apply(value);
        zoomValue.value = value;
    } catch {
        controlMessage.value = 'Zoom kamera tidak dapat diubah.';
    }
}

async function scanFromImage(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    errorMessage.value = '';
    controlMessage.value = '';
    isStarting.value = true;
    detectionLocked = false;
    resetCameraControls();

    try {
        await stopScanner();
        await nextTick();
        scanner = await createScanner();
        const result = await scanner.scanFileV2(file, true);
        await handleDetection(result.decodedText);
    } catch {
        errorMessage.value =
            'Barcode pada foto belum terbaca. Gunakan foto yang tajam, terang, dan menampilkan seluruh garis barcode.';
    } finally {
        isStarting.value = false;
    }
}

async function close(): Promise<void> {
    await stopScanner();
    emit('close');
}

onMounted(startScanner);
onBeforeUnmount(stopScanner);
</script>

<template>
    <div
        class="barcode-scanner-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="barcode-scanner-title"
    >
        <section class="barcode-scanner-panel">
            <header>
                <div>
                    <p>PEMINDAI BARCODE</p>
                    <h2 id="barcode-scanner-title">
                        Arahkan kamera ke barcode
                    </h2>
                </div>
                <button type="button" title="Tutup pemindai" @click="close">
                    <X :size="21" />
                </button>
            </header>

            <div class="barcode-scanner-body">
                <div class="barcode-camera-stage">
                    <div :id="readerId" class="barcode-camera-frame"></div>
                    <div v-if="isStarting" class="barcode-camera-status">
                        <Camera :size="28" />
                        <strong>Menyiapkan pemindai...</strong>
                    </div>
                    <div v-if="errorMessage" class="barcode-camera-error">
                        <strong>{{ errorMessage }}</strong>
                        <button type="button" @click="startScanner">
                            <RefreshCw :size="16" /> Coba kamera kembali
                        </button>
                    </div>
                </div>
                <div class="barcode-camera-controls">
                    <button
                        v-if="supportsTorch"
                        type="button"
                        :class="{ active: torchEnabled }"
                        @click="toggleTorch"
                    >
                        <Flashlight :size="17" />
                        {{ torchEnabled ? 'Matikan lampu' : 'Nyalakan lampu' }}
                    </button>
                    <label class="barcode-image-scan-button">
                        <ImagePlus :size="17" /> Scan dari foto
                        <input
                            type="file"
                            accept="image/*"
                            @change="scanFromImage"
                        />
                    </label>
                    <label v-if="supportsZoom" class="barcode-zoom-control">
                        <ZoomIn :size="17" />
                        <input
                            :value="zoomValue"
                            type="range"
                            :min="zoomMin"
                            :max="zoomMax"
                            :step="zoomStep"
                            aria-label="Zoom kamera"
                            @input="updateZoom"
                        />
                    </label>
                </div>
                <small v-if="controlMessage" class="barcode-control-message">{{
                    controlMessage
                }}</small>
                <p>
                    Jaga jarak sekitar 10-20 cm, pastikan seluruh garis barcode
                    masuk ke kotak, lalu tahan perangkat tetap stabil.
                </p>
            </div>
        </section>
    </div>
</template>
