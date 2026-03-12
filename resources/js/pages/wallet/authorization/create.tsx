import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { AuthorizationConsentProps, BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Wallet', href: '/wallet' },
    { title: 'Authorization', href: '/wallet/authorize' },
];

export default function AuthorizationCreate(props: AuthorizationConsentProps) {
    const [selectedClaims, setSelectedClaims] = useState<string[]>(props.matchedClaims);

    const { post, processing } = useForm({
        credential_id: props.credential.id,
        selected_claims: selectedClaims,
        client_id: props.clientId,
        nonce: props.nonce,
        response_uri: props.responseUri,
        state: props.state ?? '',
        definition_id: props.definitionId,
        descriptor_id: props.descriptorId,
    });

    const toggleClaim = (claim: string) => {
        setSelectedClaims((prev) => (prev.includes(claim) ? prev.filter((c) => c !== claim) : [...prev, claim]));
    };

    const handleApprove = () => {
        post('/wallet/authorize', {
            data: {
                credential_id: props.credential.id,
                selected_claims: selectedClaims,
                client_id: props.clientId,
                nonce: props.nonce,
                response_uri: props.responseUri,
                state: props.state ?? '',
                definition_id: props.definitionId,
                descriptor_id: props.descriptorId,
            },
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

                    <Card className="mt-4">
                        <CardHeader>
                            <CardTitle>{props.credential.vct ?? 'Credential'}</CardTitle>
                            <CardDescription className="truncate">{props.credential.issuer}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="mb-3 text-sm text-muted-foreground">
                                <span className="font-medium text-foreground">{props.clientId}</span> is requesting the following claims:
                            </p>

                            <div className="space-y-2">
                                {props.matchedClaims.map((claim) => {
                                    const isRequested = props.requestedClaims.includes(claim);
                                    return (
                                        <div key={claim} className="flex items-center gap-3 rounded-md border p-3">
                                            <Checkbox
                                                id={`claim-${claim}`}
                                                checked={selectedClaims.includes(claim)}
                                                onCheckedChange={() => toggleClaim(claim)}
                                            />
                                            <Label htmlFor={`claim-${claim}`} className="flex flex-1 items-center gap-2">
                                                <span>{claim}</span>
                                                {isRequested && (
                                                    <Badge variant="outline" className="text-xs">
                                                        requested
                                                    </Badge>
                                                )}
                                            </Label>
                                            <span className="text-sm text-muted-foreground">
                                                {typeof props.credential.disclosed_claims[claim] === 'object'
                                                    ? JSON.stringify(props.credential.disclosed_claims[claim])
                                                    : String(props.credential.disclosed_claims[claim])}
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
