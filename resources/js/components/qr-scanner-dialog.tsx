import { useEffect, useRef, useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';

interface QrScannerDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onScan: (result: string) => void;
    title: string;
    description: string;
}

export function QrScannerDialog({ open, onOpenChange, onScan, title, description }: QrScannerDialogProps) {
    const scannerRef = useRef<HTMLDivElement>(null);
    const html5QrCodeRef = useRef<any>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!open) return;

        let mounted = true;

        const startScanner = async () => {
            const { Html5Qrcode } = await import('html5-qrcode');

            if (!mounted || !scannerRef.current) return;

            const scannerId = 'qr-scanner-' + Date.now();
            scannerRef.current.id = scannerId;

            const scanner = new Html5Qrcode(scannerId);
            html5QrCodeRef.current = scanner;

            try {
                await scanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    (decodedText) => {
                        onScan(decodedText);
                        onOpenChange(false);
                    },
                    () => {},
                );
            } catch (err: any) {
                if (mounted) {
                    setError(err?.message || 'Unable to access camera');
                }
            }
        };

        setError(null);
        startScanner();

        return () => {
            mounted = false;
            if (html5QrCodeRef.current) {
                html5QrCodeRef.current.stop().catch(() => {});
                html5QrCodeRef.current = null;
            }
        };
    }, [open]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                {error ? (
                    <p className="py-8 text-center text-sm text-destructive">{error}</p>
                ) : (
                    <div ref={scannerRef} className="overflow-hidden rounded-md" />
                )}
            </DialogContent>
        </Dialog>
    );
}
