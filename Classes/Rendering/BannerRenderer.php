<?php
declare(strict_types=1);
namespace Supseven\Supi\Rendering;

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Frontend\Plugin\AbstractPlugin;

/**
 * Banner renderer
 *
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class BannerRenderer extends AbstractPlugin
{
    /**
     * @var StandaloneView
     */
    private $view;

    /**
     * @var array
     */
    private $configuration;

    /**
     * @var TypoScriptService
     */
    private $typoscriptService;

    /**
     * @var LanguageService
     */
    private $languageService;

    /**
     * @codeCoverageIgnore
     * BannerRenderer constructor.
     * @param array|null $configuration
     * @param StandaloneView|null $view
     * @param TypoScriptService|null $typoscriptService
     * @param LanguageService|null $languageService
     */
    public function __construct(array $configuration = null, StandaloneView $view = null, TypoScriptService $typoscriptService = null, LanguageService $languageService = null)
    {
        if (empty($configuration)) {
            $configuration = GeneralUtility::makeInstance(ObjectManager::class)
                ->get(ConfigurationManagerInterface::class)
                ->getConfiguration(
                    ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                    'Supi',
                    'Pi1'
                );
        }

        $this->configuration = $configuration;
        $this->view = $view ?: GeneralUtility::makeInstance(ObjectManager::class)->get(StandaloneView::class);

        if (!$languageService) {
            $languageService = GeneralUtility::makeInstance(LanguageService::class);

            if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() >= 11) {
                $languageService->init($GLOBALS['TYPO3_REQUEST']->getAttribute('language')->getTypo3Language());
            } else {
                $languageService->init($GLOBALS['TSFE']->sys_language_isocode);
            }
        }

        $this->languageService = $languageService;
        $this->typoscriptService = $typoscriptService ?? GeneralUtility::makeInstance(TypoScriptService::class);
    }

    public function overrideSettings(array $settings)
    {
        $this->configuration = array_replace_recursive($this->configuration, $settings);
    }

    public function render(): string
    {
        $this->view->getRequest()->setControllerExtensionName($this->configuration['extbase']['controllerExtensionName']);
        $this->view->setTemplateRootPaths($this->configuration['templateRootPaths']);
        $this->view->setLayoutRootPaths($this->configuration['layoutRootPaths']);
        $this->view->setPartialRootPaths($this->configuration['partialRootPaths']);
        $this->view->setTemplate($this->configuration['templateName']);
        $this->view->assignMultiple([
            'settings' => $this->configuration['settings'],
            'data'     => $this->configuration['data'] ?? null,
            'config'   => json_encode($this->compileClientConfig($this->configuration['settings'])),
        ]);

        return $this->view->render();
    }

    public function userFunc($content, $conf): string
    {
        if (is_array($conf) && !empty($conf)) {
            $overrides = $this->typoscriptService->convertTypoScriptArrayToPlainArray($conf);
            $this->overrideSettings($overrides);
        }

        if ($this->cObj && $this->cObj->data) {
            $this->configuration['data'] = $this->cObj->data;
        }

        return $this->render();
    }

    private function compileClientConfig(array $elements): array
    {
        $new = [];
        $booleans = [
            'detailed',
            'essentialIncludesYoutube',
            'essentialIncludesMaps',
            'required',
            'showOnLoad',
            'hideAutoEssential',
            'hideOverlayOnButtonCe',
            'perItemDetail',
            'perItemSelect',
            'showBigSwitch',
        ];

        foreach ($elements as $key => $value) {
            if ($key !== 'label' && $key !== 'text' && $key !== 'table') {
                if (in_array($key, $booleans, true)) {
                    $value = ((int)$value !== 0);
                }

                if (is_string($value) && strpos($value, 'LLL:') === 0) {
                    $translated = $this->languageService->sL($value);
                    $value = null;

                    if ($translated && $translated !== $value) {
                        $value = $translated;
                    }
                }

                if (is_array($value)) {
                    $value = $this->compileClientConfig($value);
                }

                if (($value !== null && $value !== '') || (is_array($value) && count($value) > 0)) {
                    $new[$key] = $value;
                }
            }
        }

        unset(
            $new['button'],
            $new['content'],
            $new['detail'],
            $new['gdpr'],
            $new['overview']
        );

        return $new;
    }
}
