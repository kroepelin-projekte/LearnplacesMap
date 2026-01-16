<?php

declare(strict_types=1);

use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ILIAS\GlobalScreen\Services;
use Psr\Http\Message\ServerRequestInterface;
use Kpg\Plugins\LearnplacesMap\PageEditor\PageComponent\PageComponentService;
use Kpg\Plugins\LearnplacesMap\PageEditor\Tour\TourModel;
use Kpg\Plugins\LearnplacesMap\PageEditor\Collection\CollectionModel;
use Kpg\Plugins\LearnplacesMap\PageEditor\Tour\TourView;
use Kpg\Plugins\LearnplacesMap\PageEditor\Collection\CollectionView;

/**
 * @ilCtrl_isCalledBy ilLearnplacesMapPluginGUI: ilPCPluggedGUI
 */
class ilLearnplacesMapPluginGUI extends ilPageComponentPluginGUI
{
    private const INSERT = 'insert';
    private const CREATE = 'create';

    private ilCtrlInterface $ctrl;
    private ilGlobalTemplateInterface $tpl;
    private Services $globalscreen;
    private ServerRequestInterface $request;
    protected Factory $factory;
    protected Renderer $renderer;
    private PageComponentService $mode_service;

    public function __construct()
    {
        parent::__construct();
        global $DIC;

        $this->request = $DIC->http()->request();
        $this->ctrl = $DIC->ctrl();
        $this->globalscreen = $DIC->globalScreen();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->mode_service = new PageComponentService($DIC, $this->factory);
    }

    public function executeCommand(): void
    {
        // Redirect if Learnplaces plugin is not active
        $learnplaces_plugin_is_active = \ilObjectPlugin::getPluginObjectByType('xsrl')->isActive();
        if (!$learnplaces_plugin_is_active) {
            $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_FAILURE, 'Learnplaces plugin is not active.', true);
            $this->returnToParent();
        }

        $this->hideToolMenu();
        $this->tpl->setTitle($this->plugin->txt('learnplaces_map'));

        $cmd = $this->ctrl->getCmd();
        $next_class = $this->ctrl->getNextClass();

        switch ($next_class) {
            case strtolower(ilLearnplacesMapTourGUI::class):
            case strtolower(ilLearnplacesMapCollectionGUI::class):
                $this->ctrl->forwardCommand(new $next_class($this));
                break;
            default:
                switch ($cmd) {
                    case self::INSERT:
                    case self::CREATE:
                        $this->$cmd();
                        break;
                }
        }
    }

    /**
     * Select Map Mode (tour, collection)
     *
     * @throws ilCtrlException
     */
    public function insert(): void
    {
        $this->hideToolMenu();

        $form = $this->mode_service->getModeForm(
            $this->ctrl->getFormAction($this, self::CREATE),
        );

        $this->tpl->setContent($this->renderer->render([
            $form,
        ]));
    }

    /**
     * @throws ilCtrlException
     * @throws ilException
     */
    public function edit(): void
    {
        $mode = $this->getProperties()['mode'];

        switch ($mode) {
            case PageComponentService::MODE_TOUR:
                $this->ctrl->redirectByClass(ilLearnplacesMapTourGUI::class, ilLearnplacesMapTourGUI::TOUR_VIEW);
                break;
            case PageComponentService::MODE_COLLECTION:
                $this->ctrl->redirectByClass(ilLearnplacesMapCollectionGUI::class, ilLearnplacesMapCollectionGUI::COLLECTION_VIEW);
                break;
            default:
                throw new ilException('invalid_mode');
        }
    }

    /**
     * @throws ilCtrlException
     */
    public function create(): void
    {
        $form = $this->mode_service->getModeForm(
            '#',
        )->withRequest($this->request);

        $form_data = $form->getData();

        if ($form->getError()) {
            $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_FAILURE, $form->getError(), true);
            $this->ctrl->redirect($this, self::INSERT);
        }

        $mode = $form_data['section']['mode'];
        $title = $form_data['section']['title'];
        $description = $form_data['section']['description'];

        $id = $this->mode_service->addMap($mode, $title, $description);

        $this->createElement([
            'id' => $id,
            'mode' => $mode,
        ]);
        $this->returnToParent();
    }

    /**
     * @return void
     */
    private function hideToolMenu(): void
    {
        $collection = $this->globalscreen->tool()->context()->current()->getAdditionalData();
        if ($collection->exists(ilCOPageEditGSToolProvider::SHOW_EDITOR)) {
            $collection->replace(ilCOPageEditGSToolProvider::SHOW_EDITOR, false);
        }
    }

    /**
     * @param string $a_mode
     * @param array  $a_properties
     * @param string $plugin_version
     * @return string
     * @throws ilException
     */
    public function getElementHTML(string $a_mode, array $a_properties, string $plugin_version): string
    {
        global $DIC;

        $learnplaces_plugin_is_active = \ilObjectPlugin::getPluginObjectByType('xsrl')->isActive();

        $edit_style = '';
        if ($a_mode === "edit") {
            $edit_style = ' style="pointer-events: none;"';

            if (!$learnplaces_plugin_is_active) {
                return "Learnplaces plugin is not active.";
            }
        }

        if (!$learnplaces_plugin_is_active) {
            return ' ';
        }

        $DIC->ui()->mainTemplate()->addCss('Customizing/global/plugins/Services/COPage/PageComponent/LearnplacesMap/style/style.css');

        // tour or collection
        $map_id = (int) $a_properties['id'];
        $mode = $a_properties['mode'];

        if ($mode === PageComponentService::MODE_TOUR) {
            $tour_model = new TourModel($DIC);
            if (!$tour_model->hasItems($map_id)) {
                if ($a_mode === "edit") {
                    return $this->getPlaceholderHtml();
                } else {
                    return ' ';
                }
            }
            $tour_view = new TourView($DIC, $this->factory, $map_id);
            return "<div$edit_style class='learnplaces-map'>" . $tour_view->getMap() . "</div>";
        } elseif ($mode === PageComponentService::MODE_COLLECTION) {
            $collection_model = new CollectionModel($DIC);
            if (!$collection_model->hasItems($map_id)) {
                if ($a_mode === "edit") {
                    return $this->getPlaceholderHtml();
                } else {
                    return ' ';
                }
            }

            $collection_view = new CollectionView($DIC, $this->factory, $map_id);
            return "<div$edit_style class='learnplaces-map'>" . $collection_view->getMap() . "</div>";
        }

        throw new ilException('invalid_mode');
    }

    /**
     * @return string
     */
    private function getPlaceholderHtml(): string
    {
        return <<<HTML
                <div class="learnplaces-map-placeholder">
                    <div class="placeholder-text">
                        {$this->plugin->txt('reopen_message')}
                    </div>
                </div>
                HTML;
    }
}
