export type SdJwtCredential = {
    id: string;
    issuer: string;
    vct: string | null;
    disclosed_claims: Record<string, unknown>;
    format: string;
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

export type MatchingCredential = {
    id: string;
    issuer: string;
    vct: string | null;
    disclosed_claims: Record<string, unknown>;
    format: string;
    created_at: string | null;
    available_claims: string[];
    descriptor_id: string;
};

export type AuthorizationConsentProps = {
    credentials: MatchingCredential[];
    requestedClaims: string[];
    clientId: string;
    nonce: string;
    responseUri: string;
    state: string | null;
    definitionId: string;
};
