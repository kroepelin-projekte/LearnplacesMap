<?php

declare(strict_types=1);

use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ILIAS\GlobalScreen\Services;
use Kpg\Plugins\LearnplacesMap\PageEditor\Mode\ModeService;
use Kpg\Plugins\LearnplacesMap\PageEditor\Tour\TourService;
use Psr\Http\Message\ServerRequestInterface;
use Kpg\Plugins\LearnplacesMap\PageEditor\Tour\TourMap;

/**
 * @ilCtrl_isCalledBy ilLearnplacesMapPluginGUI: ilPCPluggedGUI
 */
class ilLearnplacesMapPluginGUI extends ilPageComponentPluginGUI
{
    private const INSERT = 'insert';
    private const CREATE = 'create';

    private const COLLECTION_VIEW = 'collectionView';
    private const COLLECTION_CREATE = 'collectionCreate';

    private ilCtrlInterface $ctrl;
    private ilGlobalTemplateInterface $tpl;
    private Services $globalscreen;
    private ServerRequestInterface $request;
    protected Factory $factory;
    protected Renderer $renderer;
    private modeService $mode_service;

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
        $this->mode_service = new ModeService($DIC, $this->factory);
    }

    public function executeCommand(): void
    {
        $this->hideToolMenu();

        $cmd = $this->ctrl->getCmd();
        $next_class = $this->ctrl->getNextClass();

        switch ($next_class) {
            case strtolower(ilLearnplacesMapTourGUI::class):
                $this->ctrl->forwardCommand(new $next_class($this));
                break;
            default:
                switch ($cmd) {
                    case self::INSERT:
                    case self::CREATE:
                    case self::COLLECTION_VIEW:
                    case self::COLLECTION_CREATE:
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
            case ModeService::MODE_TOUR:
                $this->ctrl->redirectByClass(ilLearnplacesMapTourGUI::class, ilLearnplacesMapTourGUI::TOUR_VIEW);
                break;
            case ModeService::MODE_COLLECTION:
                $this->ctrl->redirect($this, self::COLLECTION_VIEW);
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
        $edit_style = '';
        if ($a_mode === "edit") {
            $edit_style = ' style="pointer-events: none; opacity: 0.5;"';
        }

        global $DIC;

        // tour or collection
        $id = (int) $a_properties['id'];
        $mode = $a_properties['mode'];

        if ($mode === ModeService::MODE_TOUR) {
            $map = new TourMap($DIC, $id);
            $map_component = $map->getMap();
            return "<div$edit_style>" . $DIC->ui()->renderer()->render($map_component) . "</div>";
        }

        // todo mode collection

        return "Map ID: $id, Mode: $mode";
    }
}
