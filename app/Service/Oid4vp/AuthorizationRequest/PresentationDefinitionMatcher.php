<?php

declare(strict_types=1);

namespace App\Service\Oid4vp\AuthorizationRequest;

use App\Models\SdJwtCredential;
use Illuminate\Database\Eloquent\Collection;

class PresentationDefinitionMatcher
{
    /**
     * @param  Collection<int, SdJwtCredential>  $credentials
     * @return array{credential: SdJwtCredential, requested_claims: array<int, string>, available_claims: array<int, string>, descriptor_id: string}|null
     */
    public function findMatchingCredential(array $presentationDefinition, Collection $credentials): ?array
    {
        $matches = $this->findAllMatchingCredentials($presentationDefinition, $credentials);

        return $matches[0] ?? null;
    }

    /**
     * @param  Collection<int, SdJwtCredential>  $credentials
     * @return array<int, array{credential: SdJwtCredential, requested_claims: array<int, string>, available_claims: array<int, string>, descriptor_id: string}>
     */
    public function findAllMatchingCredentials(array $presentationDefinition, Collection $credentials): array
    {
        $matches = [];
        $inputDescriptors = $presentationDefinition['input_descriptors'] ?? [];

        foreach ($inputDescriptors as $descriptor) {
            $descriptorId = $descriptor['id'] ?? 'default';
            $fields = $descriptor['constraints']['fields'] ?? [];

            $typeConstraints = [];
            $claimFields = [];

            foreach ($fields as $field) {
                if ($this->isTypeConstraint($field)) {
                    $typeConstraints[] = $field;
                } else {
                    $claimFields[] = $field;
                }
            }

            $requiredType = $this->extractTypePattern($typeConstraints);

            foreach ($credentials as $credential) {
                if (! $this->credentialMatchesType($credential, $requiredType, $descriptorId)) {
                    continue;
                }

                $availableClaims = array_keys($credential->disclosed_claims ?? []);
                $requestedClaims = $this->extractClaimNamesFromFields($claimFields);

                if (! empty($claimFields)) {
                    $matchedClaims = array_values(array_intersect($requestedClaims, $availableClaims));

                    if (empty($matchedClaims)) {
                        continue;
                    }
                }

                $matches[] = [
                    'credential' => $credential,
                    'requested_claims' => $requestedClaims,
                    'available_claims' => $availableClaims,
                    'descriptor_id' => $descriptorId,
                ];
            }
        }

        return $matches;
    }

    /**
     * @param  array<string, mixed>  $presentationDefinition
     * @return array<int, string>
     */
    public function getRequestedClaimNames(array $presentationDefinition): array
    {
        $claimNames = [];

        $inputDescriptors = $presentationDefinition['input_descriptors'] ?? [];

        foreach ($inputDescriptors as $descriptor) {
            $fields = $descriptor['constraints']['fields'] ?? [];

            foreach ($fields as $field) {
                if ($this->isTypeConstraint($field)) {
                    continue;
                }

                $paths = $field['path'] ?? [];

                foreach ($paths as $path) {
                    $claimName = $this->extractClaimNameFromPath($path);
                    if ($claimName !== null) {
                        $claimNames[] = $claimName;
                    }
                }
            }
        }

        return array_values(array_unique($claimNames));
    }

    /**
     * @param  array<int, string>  $requestedClaims
     * @param  array<int, string>  $availableClaims
     * @return array<int, string>
     */
    public function matchClaims(array $requestedClaims, array $availableClaims): array
    {
        return array_values(array_intersect($requestedClaims, $availableClaims));
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function isTypeConstraint(array $field): bool
    {
        $paths = $field['path'] ?? [];

        foreach ($paths as $path) {
            if (preg_match('/\.(type|vct)$/', $path) || $path === '$.type' || $path === '$.vct') {
                return true;
            }
        }

        return isset($field['filter']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $typeConstraints
     */
    private function extractTypePattern(array $typeConstraints): ?string
    {
        foreach ($typeConstraints as $field) {
            $filter = $field['filter'] ?? [];

            if (isset($filter['pattern'])) {
                return $filter['pattern'];
            }

            if (isset($filter['const'])) {
                return $filter['const'];
            }
        }

        return null;
    }

    private function credentialMatchesType(SdJwtCredential $credential, ?string $requiredType, string $descriptorId): bool
    {
        if ($requiredType === null) {
            return $credential->vct !== null && stripos($credential->vct, $descriptorId) !== false;
        }

        if ($credential->vct !== null && preg_match('/'.preg_quote($requiredType, '/').'/i', $credential->vct)) {
            return true;
        }

        $issuerTypes = $credential->issuer_claims['type'] ?? [];
        if (is_array($issuerTypes) && in_array($requiredType, $issuerTypes, true)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, string>
     */
    private function extractClaimNamesFromFields(array $fields): array
    {
        $claimNames = [];

        foreach ($fields as $field) {
            $paths = $field['path'] ?? [];

            foreach ($paths as $path) {
                $claimName = $this->extractClaimNameFromPath($path);
                if ($claimName !== null) {
                    $claimNames[] = $claimName;
                }
            }
        }

        return array_values(array_unique($claimNames));
    }

    private function extractClaimNameFromPath(string $path): ?string
    {
        if (preg_match('/^\$\.(\w+)$/', $path, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^\$\..+\.(\w+)$/', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
