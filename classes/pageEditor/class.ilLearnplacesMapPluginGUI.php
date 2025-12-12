<?php

declare(strict_types=1);

use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ILIAS\GlobalScreen\Services;
use Kpg\Plugins\LearnplacesMap\PageEditor\Mode\ModeService;
use Kpg\Plugins\LearnplacesMap\PageEditor\Tour\TourService;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @ilCtrl_isCalledBy ilLearnplacesMapPluginGUI: ilPCPluggedGUI
 */
class ilLearnplacesMapPluginGUI extends ilPageComponentPluginGUI
{
    private const INSERT = 'insert';
    private const CREATE = 'create';

    private const TOUR_VIEW = 'tourView';
    private const TOUR_CREATE = 'tourCreate';
    private const TOUR_ADD_ITEM = 'tourAddItem';
    private const TOUR_SAVE_ORDER = 'tourSaveOrder';

    private const COLLECTION_VIEW = 'collectionView';
    private const COLLECTION_CREATE = 'collectionCreate';

    private ilCtrlInterface $ctrl;
    private ilGlobalTemplateInterface $tpl;
    private Services $globalscreen;
    private ServerRequestInterface $request;
    private ilDBInterface $database;
    protected Factory $factory;
    protected Renderer $renderer;
    private modeService $mode_service;
    private tourService $tour_service;

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
        $this->database = $DIC->database();
        $this->mode_service = new ModeService($this->factory);
        $this->tour_service = new TourService($this->factory, $DIC);
    }

    public function executeCommand(): void
    {
        $cmd = $this->ctrl->getCmd();

        switch ($cmd) {
            case self::INSERT:
            case self::CREATE:
            case self::TOUR_VIEW:
            case self::TOUR_CREATE:
            case self::TOUR_ADD_ITEM:
            case self::TOUR_SAVE_ORDER:
            case self::COLLECTION_VIEW:
            case self::COLLECTION_CREATE:
                $this->$cmd();
                break;
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
                $this->ctrl->redirect($this, self::TOUR_VIEW);
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

        $id = $this->database->nextId('kpg_lmap_map');
        $this->database->insert('kpg_lmap_map', [
            'id' => ['integer', $id],
            'mode' => ['text', $mode],
        ]);

        $this->createElement([
            'id' => $id,
            'mode' => $mode,
        ]);
        $this->returnToParent();
    }

    /**
     * @throws ilCtrlException
     */
    private function tourView(): void
    {
        $this->hideToolMenu();

        $add_item_button = $this->tour_service->addItemButton(
            $this->ctrl->getLinkTargetByClass(self::class, self::TOUR_ADD_ITEM),
        );

        $table = $this->tour_service->getTourTable(
            $this->ctrl->getLinkTargetByClass(self::class, self::TOUR_SAVE_ORDER),
        );

        $this->tpl->setContent($this->renderer->render([
            $add_item_button['modal'],
            $add_item_button['button'],
            $this->factory->divider()->horizontal(),
            $table,
        ]));
    }

    /**
     * @throws ilCtrlException
     */
    private function tourCreate(): void
    {
        $form = $this->tour_service->getTourForm(
            '#',
        )->withRequest($this->request);

        $form->getData();

        if ($form->getError()) {
            $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_FAILURE, $form->getError(), true);
            $this->ctrl->redirect($this, self::TOUR_VIEW);
        }

        $this->updateElement([
            'mode' => 'tour',
        ]);
        $this->returnToParent();
    }

    private function tourSaveOrder()
    {
        $d = 0;
    }

    private function tourAddItem(): void
    {
        $map_id = (int) $this->getProperties()['id'];

        $next_id = $this->database->nextId('kpg_lmap_tour');

        $this->database->insert('kpg_lmap_tour', [
            'id' => ['integer', $next_id],
            'map_id' => ['integer', $map_id],
            'position' => ['integer', $next_id],
        ]);

        $this->ctrl->redirect($this, self::TOUR_VIEW);
    }

    public function getElementHTML(string $a_mode, array $a_properties, string $plugin_version): string
    {
        if ($a_mode !== "edit") {
            return "edit";
        }

        // tour or collection
        $id = (int) $a_properties['id'];
        $mode = $a_properties['mode'];

        return "Map ID: $id, Mode: $mode";
    }

    private function hideToolMenu(): void
    {
        $collection = $this->globalscreen->tool()->context()->current()->getAdditionalData();
        if ($collection->exists(ilCOPageEditGSToolProvider::SHOW_EDITOR)) {
            $collection->replace(ilCOPageEditGSToolProvider::SHOW_EDITOR, false);
        }
    }
}