<?php

declare(strict_types=1);

namespace Itw\Gatecha\ViewHelpers\Form;

use Itw\Gatecha\Configuration\GatechaSettings;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Fluid\ViewHelpers\Form\AbstractFormFieldViewHelper;

final class WidgetViewHelper extends AbstractFormFieldViewHelper
{
    protected $tagName = 'altcha-widget';
    /**
     * Constructor
     */
    public function __construct(private readonly AssetCollector $assetCollector)
    {
        parent::__construct();
    }

    public function render(): string
    {
        $request = $this->renderingContext->hasAttribute(ServerRequestInterface::class)
            ? $this->renderingContext->getAttribute(ServerRequestInterface::class)
            : ($GLOBALS['TYPO3_REQUEST'] ?? null);
        $settings = GatechaSettings::fromRequest($request instanceof ServerRequestInterface ? $request : null);

        $assetCollector = $this->assetCollector;
        $assetCollector->addJavaScript('gatecha-widget', 'EXT:gatecha/Resources/Public/JavaScript/altcha.min.js', ['defer' => 'defer']);
        if ($settings->isConfigured() && $settings->his) {
            $assetCollector->addJavaScript('gatecha-his', $settings->hisScriptUrl(), ['defer' => 'defer']);
        }

        $name = $this->getName();
        $this->registerFieldNameForFormTokenGeneration($name);

        $this->tag->addAttribute('name', $name);
        $this->tag->addAttribute('challenge', $settings->isConfigured() ? $settings->challengeUrl() : '');
        $this->tag->addAttribute('type', $settings->type);
        $this->tag->addAttribute('display', $settings->display);
        $this->tag->addAttribute('configuration', (string)json_encode([
            'hideLogo' => $settings->hideLogo,
            'hideFooter' => $settings->hideFooter,
        ], JSON_THROW_ON_ERROR));
        $this->tag->forceClosingTag(true);

        return $this->tag->render();
    }
}
