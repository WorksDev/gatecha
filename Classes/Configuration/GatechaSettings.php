<?php

declare(strict_types=1);

namespace Itw\Gatecha\Configuration;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

final readonly class GatechaSettings
{
    private function __construct(
        public string $url,
        public string $apiKey,
        public bool $his,
        public bool $failOpen,
        public int $timeout,
        public string $type,
        public string $display,
        public bool $hideLogo,
        public bool $hideFooter,
    ) {}

    public static function fromRequest(?ServerRequestInterface $request): self
    {
        $site = $request?->getAttribute('site');
        $settings = $site instanceof Site ? $site->getSettings() : null;
        $get = static fn(string $key, mixed $default): mixed => $settings?->get('gatecha.' . $key, $default) ?? $default;

        return new self(
            url: rtrim(trim((string)$get('url', '')), '/'),
            apiKey: trim((string)$get('apiKey', '')),
            his: (bool)$get('his', true),
            failOpen: (bool)$get('failOpen', false),
            timeout: max(1, (int)$get('timeout', 5)),
            type: (string)$get('type', 'checkbox'),
            display: (string)$get('display', 'standard'),
            hideLogo: (bool)$get('hideLogo', true),
            hideFooter: (bool)$get('hideFooter', true),
        );
    }

    public static function fromGlobalRequest(): self
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        return self::fromRequest($request instanceof ServerRequestInterface ? $request : null);
    }

    public function isConfigured(): bool
    {
        return $this->url !== '' && $this->apiKey !== '';
    }

    public function challengeUrl(): string
    {
        return $this->url . '/api/v1/challenge?apiKey=' . rawurlencode($this->apiKey);
    }

    public function verifyUrl(): string
    {
        return $this->url . '/api/v1/verify?apiKey=' . rawurlencode($this->apiKey);
    }

    public function hisScriptUrl(): string
    {
        return $this->url . '/api/public/his.js';
    }
}
