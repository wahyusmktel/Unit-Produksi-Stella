<script setup lang="ts">
import { Camera, RefreshCw, X } from '@lucide/vue';
import type { Html5Qrcode as Html5QrcodeInstance } from 'html5-qrcode';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const emit = defineEmits<{
    detected: [value: string];
    close: [];
}>();

const readerId = 'product-barcode-camera-reader';
const errorMessage = ref('');
const isStarting = ref(false);
let scanner: Html5QrcodeInstance | null = null;

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

async function startScanner(): Promise<void> {
    errorMessage.value = '';
    isStarting.value = true;
    await stopScanner();
    await nextTick();

    if (!window.isSecureContext) {
        errorMessage.value =
            'Kamera hanya dapat digunakan melalui HTTPS atau localhost.';
        isStarting.value = false;

        return;
    }

    try {
        const { Html5Qrcode, Html5QrcodeSupportedFormats } =
            await import('html5-qrcode');

        scanner = new Html5Qrcode(readerId, {
            formatsToSupport: [
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.UPC_E,
                Html5QrcodeSupportedFormats.CODE_39,
                Html5QrcodeSupportedFormats.CODE_93,
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.ITF,
                Html5QrcodeSupportedFormats.CODABAR,
                Html5QrcodeSupportedFormats.QR_CODE,
            ],
            verbose: false,
        });

        await scanner.start(
            { facingMode: 'environment' },
            {
                fps: 10,
                qrbox: { width: 260, height: 150 },
                aspectRatio: 1.7778,
            },
            async (decodedText) => {
                const value = decodedText.trim();

                if (!value) {
                    return;
                }

                emit('detected', value);
                await stopScanner();
            },
            () => undefined,
        );
    } catch (error) {
        errorMessage.value =
            error instanceof Error && error.name === 'NotAllowedError'
                ? 'Izin kamera ditolak. Aktifkan izin kamera pada browser lalu coba kembali.'
                : 'Kamera tidak dapat dibuka. Pastikan kamera tersedia dan tidak sedang digunakan aplikasi lain.';
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
                <div :id="readerId" class="barcode-camera-frame"></div>
                <div v-if="isStarting" class="barcode-camera-status">
                    <Camera :size="28" />
                    <strong>Menyiapkan kamera...</strong>
                </div>
                <div v-if="errorMessage" class="barcode-camera-error">
                    <strong>{{ errorMessage }}</strong>
                    <button type="button" @click="startScanner">
                        <RefreshCw :size="16" /> Coba kembali
                    </button>
                </div>
                <p>
                    Posisikan seluruh garis barcode di dalam kotak. Nomor akan
                    terisi otomatis setelah terdeteksi.
                </p>
            </div>
        </section>
    </div>
</template>
