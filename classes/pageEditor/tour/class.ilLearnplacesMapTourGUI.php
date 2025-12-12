<?php

declare(strict_types=1);

use Kpg\Plugins\LearnplacesMap\PageEditor\Tour\TourService;


/**
 * @ilCtrl_IsCalledBy ilLearnplacesMapTourGUI: ilLearnplacesMapPluginGUI
 */
class ilLearnplacesMapTourGUI
{
    public const TOUR_VIEW = 'tourView';
    public const TOUR_CREATE = 'tourCreate';
    public const TOUR_ADD_ITEM = 'tourAddItem';
    public const TOUR_DELETE_ITEM = 'tourDeleteItem';
    public const TOUR_SAVE_ORDER = 'tourSaveOrder';

    private ilCtrlInterface $ctrl;
    private TourService $tour_service;
    private \ILIAS\UI\Factory $factory;
    private \ILIAS\UI\Renderer $renderer;
    private ilGlobalTemplateInterface $tpl;
    private \Psr\Http\Message\ServerRequestInterface|\Psr\Http\Message\RequestInterface $request;
    private \ILIAS\HTTP\Services $http;
    private \ILIAS\Refinery\Factory $refinery;

    public function __construct(
        protected ilLearnplacesMapPluginGUI $parent_gui,
    )
    {
        global $DIC;
        $this->refinery = $DIC->refinery();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->ctrl = $DIC->ctrl();
        $this->http = $DIC->http();
        $this->request = $DIC->http()->request();
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->tour_service = new TourService($this->factory, $DIC);
    }

    public function executeCommand(): void
    {
        $cmd = $this->ctrl->getCmd();

        switch ($cmd) {
            case self::TOUR_VIEW:
            case self::TOUR_CREATE:
            case self::TOUR_ADD_ITEM:
            case self::TOUR_DELETE_ITEM:
            case self::TOUR_SAVE_ORDER:
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

        $this->parent_gui->updateElement([
            'mode' => 'tour',
        ]);
        $this->parent_gui->returnToParent();
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

        $map_id = (int) $this->parent_gui->getProperties()['id'];

        $this->tour_service->addItem($map_id, $ref_id);

        $this->ctrl->redirect($this, self::TOUR_VIEW);
    }

    private function tourDeleteItem(): void
    {
        $map_id = (int) $this->parent_gui->getProperties()['id'];

        $query = $this->http->wrapper()->query();
        if ($query->has('delete_ids')) {
            $ids = $query->retrieve('delete_ids', $this->refinery->kindlyTo()->listOf($this->refinery->kindlyTo()->string()));

            if (($ids[0] ?? null) === 'ALL_OBJECTS') {
                $this->tour_service->deleteAllItems($map_id);
            } else {
                $ids = array_map('intval', $ids);
                $this->tour_service->deleteItems($map_id, $ids);
            }
            $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_SUCCESS, 'Erfolgreich gelöscht', true);
        } else {
            $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_FAILURE, 'Es wurden keine Lernorte ausgewählt.', true);
        }

        $this->ctrl->redirect($this, self::TOUR_VIEW);
    }
}
