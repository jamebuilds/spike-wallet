import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { AuthorizationConsentProps, BreadcrumbItem, MatchingCredential } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Wallet', href: '/wallet' },
    { title: 'Authorization', href: '/wallet/authorize' },
];

export default function AuthorizationCreate(props: AuthorizationConsentProps) {
    const [selectedIndex, setSelectedIndex] = useState(0);
    const credential = props.credentials[selectedIndex];
    const isSdJwt = credential.format === 'vc+sd-jwt';
    const [selectedClaims, setSelectedClaims] = useState<string[]>(credential.available_claims);

    const [processing, setProcessing] = useState(false);

    const selectCredential = (index: number) => {
        setSelectedIndex(index);
        setSelectedClaims(props.credentials[index].available_claims);
    };

    const toggleClaim = (claim: string) => {
        if (!isSdJwt) return;
        setSelectedClaims((prev) => (prev.includes(claim) ? prev.filter((c) => c !== claim) : [...prev, claim]));
    };

    const handleApprove = () => {
        setProcessing(true);
        router.post('/wallet/authorize', {
            credential_id: credential.id,
            selected_claims: selectedClaims,
            client_id: props.clientId,
            nonce: props.nonce,
            response_uri: props.responseUri,
            state: props.state ?? '',
            definition_id: props.definitionId,
            descriptor_id: credential.descriptor_id,
        }, {
            onFinish: () => setProcessing(false),
        });
    };

    const handleDeny = () => {
        router.visit('/wallet');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Authorization Request" />

            <div className="flex h-full flex-1 flex-col items-center justify-center gap-6 p-4">
                <div className="w-full max-w-md">
                    <Heading title="Authorization Request" description="A verifier is requesting access to your credential" />

                    {props.credentials.length > 1 && (
                        <div className="mt-4">
                            <p className="mb-2 text-sm font-medium">Select a credential to present:</p>
                            <div className="space-y-2">
                                {props.credentials.map((cred, index) => (
                                    <button
                                        key={cred.id}
                                        type="button"
                                        onClick={() => selectCredential(index)}
                                        className={`w-full rounded-md border p-3 text-left transition-colors ${
                                            index === selectedIndex
                                                ? 'border-primary bg-primary/5'
                                                : 'hover:bg-accent'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm font-medium">{cred.vct ?? 'Credential'}</span>
                                            <Badge variant={cred.format === 'vc+sd-jwt' ? 'default' : 'outline'} className="text-[10px]">
                                                {cred.format === 'vc+sd-jwt' ? 'SD-JWT' : 'JWT'}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 truncate text-xs text-muted-foreground">{cred.issuer}</p>
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}

                    <Card className="mt-4">
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle>{credential.vct ?? 'Credential'}</CardTitle>
                                <Badge variant={isSdJwt ? 'default' : 'outline'} className="text-[10px]">
                                    {isSdJwt ? 'SD-JWT' : 'JWT'}
                                </Badge>
                            </div>
                            <CardDescription className="truncate">{credential.issuer}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="mb-3 text-sm text-muted-foreground">
                                <span className="font-medium text-foreground">{props.clientId}</span> is requesting the following claims:
                            </p>

                            {!isSdJwt && (
                                <p className="mb-2 text-xs text-muted-foreground">
                                    This credential does not support selective disclosure. All claims will be shared.
                                </p>
                            )}

                            <div className="space-y-2">
                                {credential.available_claims.map((claim) => {
                                    const isRequested = props.requestedClaims.includes(claim);
                                    return (
                                        <div key={claim} className="flex items-center gap-3 rounded-md border p-3">
                                            {isSdJwt ? (
                                                <Checkbox
                                                    id={`claim-${claim}`}
                                                    checked={selectedClaims.includes(claim)}
                                                    onCheckedChange={() => toggleClaim(claim)}
                                                />
                                            ) : (
                                                <Checkbox id={`claim-${claim}`} checked disabled />
                                            )}
                                            <Label htmlFor={`claim-${claim}`} className="flex flex-1 items-center gap-2">
                                                <span>{claim}</span>
                                                {isRequested && (
                                                    <Badge variant="outline" className="text-xs">
                                                        requested
                                                    </Badge>
                                                )}
                                            </Label>
                                            <span className="text-sm text-muted-foreground">
                                                {typeof credential.disclosed_claims[claim] === 'object'
                                                    ? JSON.stringify(credential.disclosed_claims[claim])
                                                    : String(credential.disclosed_claims[claim])}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>

                    <div className="mt-6 flex gap-3">
                        <Button variant="outline" onClick={handleDeny} className="flex-1">
                            Deny
                        </Button>
                        <Button onClick={handleApprove} disabled={processing || selectedClaims.length === 0} className="flex-1">
                            {processing ? 'Submitting...' : 'Approve'}
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
