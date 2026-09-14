<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Mercure;

use InvalidArgumentException;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\Base64UrlSafe;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256GCM;
use Jose\Component\Encryption\Algorithm\KeyEncryption\Dir;
use Jose\Component\Encryption\JWEBuilder;
use Jose\Component\Encryption\Serializer\CompactSerializer;
use LogicException;
use SensitiveParameter;

/**
 * Channel Encrypter
 *
 * Encrypts Mercure updates end to end (JWE `dir` + `A256GCM`) so the hub
 * never sees plaintext. Requires `web-token/jwt-library`.
 */
class ChannelEncrypter
{
    /**
     * Lazily built JWE builder, reused across updates.
     *
     * @var \Jose\Component\Encryption\JWEBuilder|null
     */
    protected ?JWEBuilder $jweBuilder = null;

    /**
     * Lazily built compact JWE serializer.
     *
     * @var \Jose\Component\Encryption\Serializer\CompactSerializer|null
     */
    protected ?CompactSerializer $serializer = null;

    /**
     * Create a new channel encrypter.
     *
     * @param string $key 32-byte raw encryption key (`base64:` prefix accepted)
     * @throws \LogicException When `web-token/jwt-library` is not installed
     * @throws \InvalidArgumentException When the key is not exactly 32 bytes
     */
    public function __construct(
        #[SensitiveParameter] protected string $key,
    ) {
        if (!class_exists(JWEBuilder::class)) {
            throw new LogicException(
                'web-token/jwt-library is required to use end-to-end encrypted Mercure channels. ' .
                'You may install it via: composer require web-token/jwt-library',
            );
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            $this->key = $decoded === false ? $key : $decoded;
        }

        if (strlen($this->key) !== 32) {
            throw new InvalidArgumentException(
                'The Mercure channel encryption key must be exactly 32 bytes.',
            );
        }
    }

    /**
     * Encrypt plaintext for a channel as compact JWE.
     *
     * @param string $plaintext Payload to encrypt
     * @param string $channel Channel name used as HKDF info for key derivation
     * @return string Compact JWE ciphertext
     */
    public function encrypt(string $plaintext, string $channel): string
    {
        $this->jweBuilder ??= new JWEBuilder(new AlgorithmManager([new Dir(), new A256GCM()]));
        $this->serializer ??= new CompactSerializer();

        $jwe = $this->jweBuilder->create()
            ->withPayload($plaintext)
            ->withSharedProtectedHeader(['alg' => 'dir', 'enc' => 'A256GCM'])
            ->addRecipient(new JWK([
                'kty' => 'oct',
                'k' => Base64UrlSafe::encodeUnpadded($this->channelKey($channel)),
            ]))
            ->build();

        return $this->serializer->serialize($jwe, 0);
    }

    /**
     * Derive the AES-256-GCM key of a channel.
     *
     * @param string $channel Channel name
     * @return string Raw 32-byte key
     */
    public function channelKey(string $channel): string
    {
        return hash_hkdf('sha256', $this->key, 32, $channel);
    }

    /**
     * Build the JWK shared with an authorized subscriber.
     *
     * @param string $channel Channel name
     * @return array{kty: string, k: string, alg: string, use: string}
     */
    public function channelJwk(string $channel): array
    {
        return [
            'kty' => 'oct',
            'k' => Base64UrlSafe::encodeUnpadded($this->channelKey($channel)),
            'alg' => 'A256GCM',
            'use' => 'enc',
        ];
    }
}
