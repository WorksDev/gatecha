<?php

declare(strict_types=1);

namespace Itw\Gatecha\Validation;

use Itw\Gatecha\Configuration\GatechaSettings;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

final class GatechaValidator extends AbstractValidator
{
    private const string LLL = 'LLL:EXT:gatecha/Resources/Private/Language/locallang.xlf:';
    private const string HIS_FIELD = 'gatecha_his_signals';

    protected $acceptsEmptyValues = false;
    public function __construct(private readonly LogManager $logManager)
    {
    }

    protected function isValid(mixed $value): void
    {
        $request = $this->request ?? ($GLOBALS['TYPO3_REQUEST'] ?? null);
        $request = $request instanceof ServerRequestInterface ? $request : null;
        $settings = GatechaSettings::fromRequest($request);

        if (!$settings->isConfigured()) {
            $this->getLogger()->error('GateCHA is not configured (gatecha.url / gatecha.apiKey)');
            $this->failOrPass($settings, 'error.unavailable', 1792200001);
            return;
        }

        $payload = is_string($value) ? trim($value) : '';
        if ($payload === '') {
            $this->addError($this->translateErrorMessage(self::LLL . 'error.missing'), 1792200002);
            return;
        }

        $body = ['payload' => $payload];
        if ($settings->his) {
            $signals = $this->getHisSignals($request);
            if ($signals !== null) {
                $body['his_signals'] = $signals;
            }
        }

        $headers = ['Accept' => 'application/json'];
        $clientIp = $this->getClientIp($request);
        if ($clientIp !== '') {
            $headers['X-Forwarded-For'] = $clientIp;
        }

        try {
            $response = GeneralUtility::makeInstance(RequestFactory::class)->request($settings->verifyUrl(), 'POST', [
                'headers' => $headers,
                'json' => $body,
                'timeout' => $settings->timeout,
                'connect_timeout' => $settings->timeout,
                'allow_redirects' => false,
                'http_errors' => false,
            ]);
            $data = json_decode((string)$response->getBody(), true);
        } catch (\Throwable $e) {
            $this->getLogger()->error('GateCHA verify request failed', ['exception' => $e]);
            $this->failOrPass($settings, 'error.unavailable', 1792200003);
            return;
        }

        if (!is_array($data) || !array_key_exists('ok', $data)) {
            $this->getLogger()->error('GateCHA returned an unexpected response', ['status' => $response->getStatusCode()]);
            $this->failOrPass($settings, 'error.unavailable', 1792200004);
            return;
        }

        if ($data['ok'] === true) {
            return;
        }

        $error = (string)($data['error'] ?? '');
        $this->getLogger()->info('GateCHA rejected a submission', [
            'error' => $error,
            'his_bot_score' => $data['his_bot_score'] ?? null,
        ]);

        $messageKey = match ($error) {
            'bot_suspected' => 'error.botSuspected',
            'expired', 'challenge_expired' => 'error.expired',
            default => 'error.invalid',
        };
        $this->addError($this->translateErrorMessage(self::LLL . $messageKey), 1792200005);
    }

    private function failOrPass(GatechaSettings $settings, string $messageKey, int $code): void
    {
        if ($settings->failOpen) {
            return;
        }
        $this->addError($this->translateErrorMessage(self::LLL . $messageKey), $code);
    }

    /**
     * @return array<string, int|float>|null
     */
    private function getHisSignals(?ServerRequestInterface $request): ?array
    {
        $parsedBody = $request?->getParsedBody();
        $raw = is_array($parsedBody) ? ($parsedBody[self::HIS_FIELD] ?? null) : null;
        if (!is_string($raw) || $raw === '' || strlen($raw) > 4096) {
            return null;
        }
        $signals = json_decode($raw, true);
        if (!is_array($signals)) {
            return null;
        }
        return array_filter($signals, static fn(mixed $v): bool => is_int($v) || is_float($v));
    }

    private function getClientIp(?ServerRequestInterface $request): string
    {
        $normalizedParams = $request?->getAttribute('normalizedParams');
        return $normalizedParams instanceof NormalizedParams ? $normalizedParams->getRemoteAddress() : '';
    }

    private function getLogger(): LoggerInterface
    {
        return $this->logManager->getLogger(self::class);
    }
}
