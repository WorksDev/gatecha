<?php

declare(strict_types=1);

namespace Itw\Gatecha\EventListener;

use Itw\Gatecha\Configuration\GatechaSettings;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Directive;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Event\PolicyMutatedEvent;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\UriValue;

#[AsEventListener(identifier: 'gatecha/content-security-policy')]
final readonly class ContentSecurityPolicyListener
{
    public function __invoke(PolicyMutatedEvent $event): void
    {
        if (!$event->scope->type->isFrontend()) {
            return;
        }

        $settings = GatechaSettings::fromRequest($event->request);
        if (!$settings->isConfigured()) {
            return;
        }

        $host = new UriValue($settings->url);
        $event->setCurrentPolicy(
            $event->getCurrentPolicy()
                ->extend(Directive::ScriptSrc, $host)
                ->extend(Directive::ConnectSrc, $host)
        );
    }
}
