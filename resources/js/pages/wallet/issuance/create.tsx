import { Head, router, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, IssuanceOfferProps } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Wallet', href: '/wallet' },
    { title: 'Receive Credential', href: '/wallet/receive' },
];

export default function IssuanceCreate(props: IssuanceOfferProps) {
    const { post, processing } = useForm({
        credential_issuer: props.credentialIssuer,
        credential_endpoint: props.credentialEndpoint,
        token_endpoint: props.tokenEndpoint,
        pre_authorized_code: props.preAuthorizedCode,
        credential_configuration_id: props.credentialConfigurationId,
        credential_format: props.credentialFormat,
        credential_type: props.credentialType ?? '',
        credential_definition: props.credentialDefinition,
        tx_code: props.txCode ?? '',
    });

    const handleAccept = () => {
        post('/wallet/receive');
    };

    const handleDecline = () => {
        router.visit('/wallet');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Receive Credential" />

            <div className="flex h-full flex-1 flex-col items-center justify-center gap-6 p-4">
                <div className="w-full max-w-md">
                    <Heading title="Credential Offer" description="An issuer wants to provide you with a credential" />

                    <Card className="mt-4">
                        <CardHeader>
                            <CardTitle>{props.credentialType ?? 'Verifiable Credential'}</CardTitle>
                            <CardDescription className="truncate">{props.credentialIssuer}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div>
                                <span className="font-medium">Format:</span> {props.credentialFormat}
                            </div>
                            {props.credentialConfigurationId && (
                                <div>
                                    <span className="font-medium">Configuration:</span> {props.credentialConfigurationId}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <div className="mt-6 flex gap-3">
                        <Button variant="outline" onClick={handleDecline} className="flex-1">
                            Decline
                        </Button>
                        <Button onClick={handleAccept} disabled={processing} className="flex-1">
                            {processing ? 'Receiving...' : 'Accept'}
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
