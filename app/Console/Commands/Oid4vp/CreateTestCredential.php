<?php

declare(strict_types=1);

namespace App\Console\Commands\Oid4vp;

use App\Models\SdJwtCredential;
use App\Models\User;
use App\Service\Oid4vp\HolderKeyService;
use App\Service\Oid4vp\SdJwt\Disclosure;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;

class CreateTestCredential extends Command
{
    private const ISSUER_PRIVATE_KEY = '-----BEGIN EC PRIVATE KEY-----
MHcCAQEEIKmvIJLqWs/34LWneO9gBJOCkQjS9Y9ONYBhv5d4sufIoAoGCCqGSM49
AwEHoUQDQgAEaHHal3pTwaJFNQ0Zn2P40CrHRI5I+HJQkW9I5105Sm/0C7MUnU10
Q/keADCI6DWRdMz2e+mdab4RZABkDPnLzg==
-----END EC PRIVATE KEY-----';

    /**
     * @var array<string, array<string, string>>
     */
    private const CLAIM_PRESETS = [
        'IdentityCredential' => [
            'given_name' => 'John',
            'family_name' => 'Doe',
            'birthdate' => '1990-01-01',
        ],
        'BankId' => [
            'given_name' => 'John',
            'family_name' => 'Doe',
            'birthdate' => '1990-01-01',
            'address' => '123 Main St, Singapore 123456',
            'nationality' => 'SG',
        ],
    ];

    protected $signature = 'oid4vp:create-test-credential
        {user_email : The email of the user to create the credential for}
        {--vct=IdentityCredential : The Verifiable Credential Type}
        {--claim=* : Additional claims as key=value pairs (e.g. --claim=name=John --claim=age=30)}';

    protected $description = 'Create a test SD-JWT-VC credential for a user.';

    public function handle(HolderKeyService $holderKeyService): int
    {
        $user = User::where('email', $this->argument('user_email'))->first();

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $holderKeyService->ensureKeyPair($user);
        $user->refresh();

        $vct = $this->option('vct');
        $claimsData = $this->buildClaims($user, $vct);

        $disclosures = [];
        foreach ($claimsData as $name => $value) {
            $disclosures[] = Disclosure::create($name, $value);
        }

        $sdArray = array_map(fn (Disclosure $d) => $d->hash(), $disclosures);

        $encoder = new JoseEncoder;
        $builder = new Builder($encoder, ChainedFormatter::withUnixTimestampDates());

        $issuerKey = InMemory::plainText(self::ISSUER_PRIVATE_KEY);
        $holderJwk = $holderKeyService->getPublicKeyJwk($user);

        $issuerDid = $this->buildIssuerDid();

        $token = $builder
            ->withHeader('typ', 'vc+sd-jwt')
            ->withHeader('kid', $issuerDid.'#0')
            ->issuedBy($issuerDid)
            ->issuedAt(new DateTimeImmutable)
            ->withClaim('vct', $vct)
            ->withClaim('_sd', $sdArray)
            ->withClaim('cnf', ['jwk' => $holderJwk])
            ->getToken(new Sha256, $issuerKey);

        $issuerJwt = $token->toString();

        $sdJwtParts = $issuerJwt.'~';
        foreach ($disclosures as $disclosure) {
            $sdJwtParts .= $disclosure->encoded.'~';
        }

        $disclosedClaims = [];
        foreach ($disclosures as $disclosure) {
            $disclosedClaims[$disclosure->claimName] = $disclosure->claimValue;
        }

        $credential = SdJwtCredential::create([
            'user_id' => $user->id,
            'raw_sd_jwt' => $sdJwtParts,
            'issuer_claims' => [
                'iss' => $issuerDid,
                'vct' => $vct,
                '_sd' => $sdArray,
            ],
            'disclosed_claims' => $disclosedClaims,
            'issuer' => $issuerDid,
            'vct' => $vct,
            'format' => 'vc+sd-jwt',
        ]);

        $this->info('SD-JWT-VC created successfully.');
        $this->line("  Credential ID: {$credential->id}");
        $this->line("  User: {$user->email}");
        $this->line("  Issuer: {$issuerDid}");
        $this->line('  Disclosures: '.implode(', ', array_keys($disclosedClaims)));

        return self::SUCCESS;
    }

    private function buildIssuerDid(): string
    {
        $key = openssl_pkey_get_private(self::ISSUER_PRIVATE_KEY);
        $details = openssl_pkey_get_details($key);

        $x = rtrim(strtr(base64_encode($details['ec']['x']), '+/', '-_'), '=');
        $y = rtrim(strtr(base64_encode($details['ec']['y']), '+/', '-_'), '=');

        $jwk = json_encode([
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => $x,
            'y' => $y,
        ], JSON_UNESCAPED_SLASHES);

        $encoded = rtrim(strtr(base64_encode($jwk), '+/', '-_'), '=');

        return 'did:jwk:'.$encoded;
    }

    /**
     * @return array<string, string>
     */
    private function buildClaims(User $user, string $vct): array
    {
        $claims = self::CLAIM_PRESETS[$vct] ?? self::CLAIM_PRESETS['IdentityCredential'];

        $claims['email'] = $user->email;

        foreach ($this->option('claim') as $pair) {
            if (str_contains($pair, '=')) {
                [$key, $value] = explode('=', $pair, 2);
                $claims[$key] = $value;
            }
        }

        return $claims;
    }
}
