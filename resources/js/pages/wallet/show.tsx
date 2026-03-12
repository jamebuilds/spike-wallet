import { Head, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, SdJwtCredential } from '@/types';

function decodeBase64Url(str: string): string {
    const padded = str.replace(/-/g, '+').replace(/_/g, '/');
    return atob(padded);
}

function decodeSdJwt(rawSdJwt: string) {
    const parts = rawSdJwt.split('~');
    const issuerJwt = parts[0];
    const [headerB64, payloadB64] = issuerJwt.split('.');

    let header = {};
    let payload = {};
    try {
        header = JSON.parse(decodeBase64Url(headerB64));
        payload = JSON.parse(decodeBase64Url(payloadB64));
    } catch {
        /* ignore parse errors */
    }

    const disclosures = parts
        .slice(1)
        .filter((p) => p !== '')
        .map((encoded) => {
            try {
                const decoded = JSON.parse(decodeBase64Url(encoded));
                return { encoded, salt: decoded[0], claim: decoded[1], value: decoded[2] };
            } catch {
                return { encoded, salt: '', claim: '', value: '' };
            }
        });

    return { header, payload, disclosures };
}

export default function WalletShow({ credential }: { credential: SdJwtCredential }) {
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const decoded = decodeSdJwt(credential.raw_sd_jwt);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Wallet', href: '/wallet' },
        { title: credential.vct ?? 'Credential', href: `/wallet/${credential.id}` },
    ];

    const handleDelete = () => {
        router.delete(`/wallet/${credential.id}`, {
            onSuccess: () => setShowDeleteDialog(false),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={credential.vct ?? 'Credential'} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
                <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0 flex-1 break-all">
                        <Heading title={credential.vct ?? 'Credential'} description={credential.issuer} />
                    </div>
                    <Button variant="destructive" size="sm" className="shrink-0" onClick={() => setShowDeleteDialog(true)}>
                        <Trash2 className="mr-1 h-4 w-4" />
                        Delete
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Claims</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            {Object.entries(credential.disclosed_claims).map(([key, value]) => (
                                <div key={key} className="flex items-start gap-3 rounded-md border p-3">
                                    <Badge variant="outline">{key}</Badge>
                                    <span className="text-sm">{typeof value === 'object' ? JSON.stringify(value) : String(value)}</span>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>JWT Header</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <pre className="overflow-x-auto rounded-md bg-muted p-3 text-xs">{JSON.stringify(decoded.header, null, 2)}</pre>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>JWT Payload</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <pre className="overflow-x-auto rounded-md bg-muted p-3 text-xs">{JSON.stringify(decoded.payload, null, 2)}</pre>
                    </CardContent>
                </Card>

                {decoded.disclosures.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Disclosures</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                {decoded.disclosures.map((d, i) => (
                                    <div key={i} className="rounded-md border p-3">
                                        <div className="mb-1 flex gap-2">
                                            <Badge variant="secondary">{d.claim}</Badge>
                                            <span className="text-sm">{typeof d.value === 'object' ? JSON.stringify(d.value) : String(d.value)}</span>
                                        </div>
                                        <p className="truncate text-xs text-muted-foreground">Salt: {d.salt}</p>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Raw SD-JWT</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <pre className="max-h-40 overflow-auto whitespace-pre-wrap break-all rounded-md bg-muted p-3 text-xs">{credential.raw_sd_jwt}</pre>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete credential</DialogTitle>
                        <DialogDescription>Are you sure you want to delete this credential? This action cannot be undone.</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowDeleteDialog(false)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleDelete}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
