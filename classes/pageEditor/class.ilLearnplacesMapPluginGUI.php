<?php

declare(strict_types=1);

use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ILIAS\GlobalScreen\Services;
use Psr\Http\Message\ServerRequestInterface;
use Kpg\Plugins\LearnplacesMap\PageEditor\Tour\TourMapView;
use Kpg\Plugins\LearnplacesMap\PageEditor\PageComponent\PageComponentService;
use Kpg\Plugins\LearnplacesMap\PageEditor\Tour\TourModel;
use Kpg\Plugins\LearnplacesMap\PageEditor\Collection\CollectionMapView;

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
        $this->hideToolMenu();
        $this->tpl->setTitle('Lernort Karte'); // todo lang

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

    private function hideToolMenu(): void
    {
        $collection = $this->globalscreen->tool()->context()->current()->getAdditionalData();
        if ($collection->exists(ilCOPageEditGSToolProvider::SHOW_EDITOR)) {
            $collection->replace(ilCOPageEditGSToolProvider::SHOW_EDITOR, false);
        }
    }

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
        $id = (int) $a_properties['id'];
        $mode = $a_properties['mode'];

        if ($mode === PageComponentService::MODE_TOUR) {
            $map = new TourMapView($DIC, $id);

            $tour_model = new TourModel($DIC, $this->factory);
            if (!$tour_model->hasItems($id)) {
                if ($a_mode === "edit") {
                    return 'LearnplacesMap - Tour: No Items';
                } else {
                    return ' ';
                }
            }

            $map_component = $map->getMap();
            return "<div$edit_style class='learnplaces-map'>" . $DIC->ui()->renderer()->render($map_component) . "</div>";
        } elseif ($mode === PageComponentService::MODE_COLLECTION) {
            $map = new CollectionMapView($DIC, $id);
            $map_component = $map->getMap();
            return "<div$edit_style class='learnplaces-map'>" . $DIC->ui()->renderer()->render($map_component) . "</div>";
        }

        throw new ilException('invalid_mode');
    }
}
