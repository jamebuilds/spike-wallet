import { Head, Link, router, usePage } from '@inertiajs/react';
import { CreditCard, Plus, QrCode, Shield } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import Heading from '@/components/heading';
import { QrScannerDialog } from '@/components/qr-scanner-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, SdJwtCredential } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Wallet',
        href: '/wallet',
    },
];

export default function WalletIndex({ credentials }: { credentials: SdJwtCredential[] }) {
    const [credentialOfferUrl, setCredentialOfferUrl] = useState('');
    const [authRequestUrl, setAuthRequestUrl] = useState('');
    const [showOfferScanner, setShowOfferScanner] = useState(false);
    const [showAuthScanner, setShowAuthScanner] = useState(false);
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;

    const handleReceiveCredential = (e: FormEvent) => {
        e.preventDefault();
        if (credentialOfferUrl.trim()) {
            router.get('/wallet/receive', { credential_offer_url: credentialOfferUrl });
        }
    };

    const handleAuthorize = (e: FormEvent) => {
        e.preventDefault();
        if (authRequestUrl.trim()) {
            router.get('/wallet/authorize', { auth_request_url: authRequestUrl });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Web Wallet" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
                <Heading title="Web Wallet" description="Manage your verifiable credentials" />

                {flash?.success && (
                    <div className="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
                        {flash.success}
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
                        {flash.error}
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Plus className="h-4 w-4" />
                                Receive Credential
                            </CardTitle>
                            <CardDescription>Paste a credential offer URL to receive a new credential</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleReceiveCredential} className="flex gap-2">
                                <div className="flex-1">
                                    <Label htmlFor="credential_offer_url" className="sr-only">
                                        Credential Offer URL
                                    </Label>
                                    <Input
                                        id="credential_offer_url"
                                        value={credentialOfferUrl}
                                        onChange={(e) => setCredentialOfferUrl(e.target.value)}
                                        placeholder="openid-credential-offer://..."
                                    />
                                </div>
                                <Button type="button" variant="outline" size="icon" onClick={() => setShowOfferScanner(true)} title="Scan QR">
                                    <QrCode className="h-4 w-4" />
                                </Button>
                                <Button type="submit" disabled={!credentialOfferUrl.trim()}>
                                    Receive
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Shield className="h-4 w-4" />
                                Authorization Request
                            </CardTitle>
                            <CardDescription>Paste an authorization request URL to present credentials</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleAuthorize} className="flex gap-2">
                                <div className="flex-1">
                                    <Label htmlFor="auth_request_url" className="sr-only">
                                        Authorization Request URL
                                    </Label>
                                    <Input
                                        id="auth_request_url"
                                        value={authRequestUrl}
                                        onChange={(e) => setAuthRequestUrl(e.target.value)}
                                        placeholder="openid4vp://..."
                                    />
                                </div>
                                <Button type="button" variant="outline" size="icon" onClick={() => setShowAuthScanner(true)} title="Scan QR">
                                    <QrCode className="h-4 w-4" />
                                </Button>
                                <Button type="submit" disabled={!authRequestUrl.trim()}>
                                    Authorize
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>

                <div>
                    <h3 className="mb-3 text-lg font-medium">Credentials</h3>

                    {credentials.length === 0 ? (
                        <Card>
                            <CardContent className="flex flex-col items-center justify-center py-12">
                                <CreditCard className="mb-3 h-10 w-10 text-muted-foreground" />
                                <p className="text-sm text-muted-foreground">No credentials yet. Receive one using a credential offer URL above.</p>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                            {credentials.map((credential) => (
                                <Link key={credential.id} href={`/wallet/${credential.id}`} className="block">
                                    <Card className="transition-colors hover:bg-accent">
                                        <CardHeader className="pb-2">
                                            <div className="flex items-center justify-between">
                                                <CardTitle className="text-sm font-medium">{credential.vct ?? 'Credential'}</CardTitle>
                                                <Badge variant={credential.format === 'vc+sd-jwt' ? 'default' : 'outline'} className="text-[10px]">
                                                    {credential.format === 'vc+sd-jwt' ? 'SD-JWT' : 'JWT'}
                                                </Badge>
                                            </div>
                                            <CardDescription className="truncate text-xs">{credential.issuer}</CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="flex flex-wrap gap-1">
                                                {Object.keys(credential.disclosed_claims).map((claim) => (
                                                    <Badge key={claim} variant="secondary" className="text-xs">
                                                        {claim}
                                                    </Badge>
                                                ))}
                                            </div>
                                            {credential.created_at && (
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    {new Date(credential.created_at).toLocaleDateString()}
                                                </p>
                                            )}
                                        </CardContent>
                                    </Card>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </div>
            <QrScannerDialog
                open={showOfferScanner}
                onOpenChange={setShowOfferScanner}
                onScan={(result) => setCredentialOfferUrl(result)}
                title="Scan Credential Offer"
                description="Point your camera at the issuer's QR code"
            />

            <QrScannerDialog
                open={showAuthScanner}
                onOpenChange={setShowAuthScanner}
                onScan={(result) => setAuthRequestUrl(result)}
                title="Scan Authorization Request"
                description="Point your camera at the verifier's QR code"
            />
        </AppLayout>
    );
}
