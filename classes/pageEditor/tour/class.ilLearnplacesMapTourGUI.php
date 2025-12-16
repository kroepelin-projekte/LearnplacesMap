<?php

declare(strict_types=1);

use Kpg\Plugins\LearnplacesMap\PageEditor\Tour\TourService;
use Kpg\Plugins\LearnplacesMap\PageEditor\Tour\TourMap;
use ILIAS\DI\Container;
use Kpg\Plugins\LearnplacesMap\PageEditor\PageComponent\PageComponentService;

/**
 * @ilCtrl_IsCalledBy ilLearnplacesMapTourGUI: ilLearnplacesMapPluginGUI
 */
class ilLearnplacesMapTourGUI
{
    public const TOUR_VIEW = 'tourView';
    public const TOUR_ADD_ITEM = 'tourAddItem';
    public const TOUR_DELETE_ITEM = 'tourDeleteItem';
    public const TOUR_SAVE_ORDER = 'tourSaveOrder';
    public const TOUR_UPDATE = 'tourUpdate';

    private ilCtrlInterface $ctrl;
    private \ILIAS\UI\Factory $factory;
    private \ILIAS\UI\Renderer $renderer;
    private ilGlobalTemplateInterface $tpl;
    private \Psr\Http\Message\ServerRequestInterface|\Psr\Http\Message\RequestInterface $request;
    private \ILIAS\HTTP\Services $http;
    private \ILIAS\Refinery\Factory $refinery;
    private Container $dic;
    private int $map_id;
    private TourService $tour_service;
    private TourMap $map_service;
    private PageComponentService $page_component_service;

    public function __construct(
        protected ilLearnplacesMapPluginGUI $parent_gui,
    )
    {
        global $DIC;
        $this->dic = $DIC;
        $this->refinery = $DIC->refinery();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->ctrl = $DIC->ctrl();
        $this->http = $DIC->http();
        $this->request = $DIC->http()->request();
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->map_id = (int) $this->parent_gui->getProperties()['id'];
        $this->tour_service = new TourService($DIC, $this->factory);
        $this->map_service = new TourMap($this->dic, $this->map_id);
        $this->page_component_service = new PageComponentService($this->dic, $this->factory);
    }

    public function executeCommand(): void
    {
        $cmd = $this->ctrl->getCmd();

        switch ($cmd) {
            case self::TOUR_VIEW:
            case self::TOUR_ADD_ITEM:
            case self::TOUR_DELETE_ITEM:
            case self::TOUR_SAVE_ORDER:
            case self::TOUR_UPDATE:
                $this->$cmd();
                break;
        }
    }

    /**
     * @throws ilCtrlException
     */
    private function tourView(): void
    {
        $add_item_button = $this->tour_service->addItemButton();

        $table = $this->tour_service->getTourTable((int) $this->parent_gui->getProperties()['id']);

        $map = $this->map_service->getMap();

        $edit_tour_form = $this->page_component_service->getMapUpdateForm(
            $this->map_id,
            $this->dic->ctrl()->getFormActionByClass(\ilLearnplacesMapTourGUI::class, \ilLearnplacesMapTourGUI::TOUR_UPDATE),
        );

        $this->tpl->setContent($this->renderer->render([
            $edit_tour_form,
            $map,
            $this->factory->divider()->horizontal(),
            $add_item_button['modal'],
            $add_item_button['button'],
            $this->factory->divider()->horizontal(),
            $table,
        ]));
    }

    private function tourSaveOrder(): void
    {
        $table = $this->tour_service->getTourTable((int) $this->parent_gui->getProperties()['id']);
        $order = $table->withRequest($this->request)->getData();

        foreach ($order as $new_position => $id) {
            $this->tour_service->updatePosition((int) $id, (int) $new_position);
        }

        $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_SUCCESS, 'Erfolgreich sortiert', true);
        $this->ctrl->redirect($this, self::TOUR_VIEW);
    }

    private function tourAddItem(): void
    {
        global $DIC;
        $modal = $this->tour_service->addItemButton()['modal'];

        $form_data = $modal->getForm()->withRequest($this->request)->getData();
        $ref_id = ((int) $form_data['ref_id']) ?? null;
        if (!$ref_id) {
            $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_FAILURE, 'Bitte wählen Sie einen Lernort aus.', true);
            $this->ctrl->redirect($this, self::TOUR_VIEW);
        }

        $this->tour_service->addItem($this->map_id, $ref_id);

        $this->ctrl->redirect($this, self::TOUR_VIEW);
    }

    private function tourDeleteItem(): void
    {
        $query = $this->http->wrapper()->query();
        if ($query->has('delete_ids')) {
            $ids = $query->retrieve('delete_ids', $this->refinery->kindlyTo()->listOf($this->refinery->kindlyTo()->string()));

            if (($ids[0] ?? null) === 'ALL_OBJECTS') {
                $this->tour_service->deleteAllItems($this->map_id);
            } else {
                $ids = array_map('intval', $ids);
                $this->tour_service->deleteItems($this->map_id, $ids);
            }
            $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_SUCCESS, 'Erfolgreich gelöscht', true);
        } else {
            $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_FAILURE, 'Es wurden keine Lernorte ausgewählt.', true);
        }

        $this->ctrl->redirect($this, self::TOUR_VIEW);
    }

    private function tourUpdate(): void
    {
        $form = $this->page_component_service->getMapUpdateForm(
            -1,
            '#',
        )->withRequest($this->request);
        $form_data = $form->getData();

        if ($form->getError()) {
            $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_FAILURE, $form->getError(), true);
            $this->ctrl->redirect($this, self::TOUR_VIEW);
        }

        $title = $form_data['section']['title'];
        $description = $form_data['section']['description'];

        $this->page_component_service->updateMap($this->map_id, $title, $description);

        $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_SUCCESS, 'Erfolgreich aktualisiert', true);
        $this->ctrl->redirect($this, self::TOUR_VIEW);
    }
}
