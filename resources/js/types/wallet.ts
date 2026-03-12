export type SdJwtCredential = {
    id: string;
    issuer: string;
    vct: string | null;
    disclosed_claims: Record<string, unknown>;
    raw_sd_jwt: string;
    created_at: string | null;
};

export type IssuanceOfferProps = {
    credentialIssuer: string;
    credentialConfigurationId: string;
    credentialFormat: string;
    credentialDefinition: { type?: string[] } | null;
    credentialType: string | null;
    tokenEndpoint: string;
    credentialEndpoint: string;
    preAuthorizedCode: string;
    txCode: string | null;
};

export type AuthorizationConsentProps = {
    credential: {
        id: string;
        issuer: string;
        vct: string | null;
        disclosed_claims: Record<string, unknown>;
        created_at: string | null;
    };
    requestedClaims: string[];
    matchedClaims: string[];
    clientId: string;
    nonce: string;
    responseUri: string;
    state: string | null;
    definitionId: string;
    descriptorId: string;
};
