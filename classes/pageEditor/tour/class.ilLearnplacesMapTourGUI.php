<?php

declare(strict_types=1);

use Kpg\Plugins\LearnplacesMap\PageEditor\Tour\TourView;
use Kpg\Plugins\LearnplacesMap\PageEditor\Tour\TourModel;
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
    public const SHOW_MAP_SETTINGS = 'showMapSettings';
    public const SAVE_MAP_SETTINGS = 'saveMapSettings';

    private ilCtrlInterface $ctrl;
    private \ILIAS\UI\Factory $factory;
    private \ILIAS\UI\Renderer $renderer;
    private ilGlobalTemplateInterface $tpl;
    private \Psr\Http\Message\ServerRequestInterface|\Psr\Http\Message\RequestInterface $request;
    private \ILIAS\HTTP\Services $http;
    private \ILIAS\Refinery\Factory $refinery;
    private Container $dic;
    private int $map_id;
    private TourView $tour_view;
    private TourModel $tour_model;
    private PageComponentService $page_component_service;
    private ilPlugin|ilLearnplacesMapPlugin $plugin;

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
        $this->tour_view = new TourView($DIC, $this->factory, $this->map_id);
        $this->tour_model = new TourModel($DIC);
        $this->page_component_service = new PageComponentService($this->dic, $this->factory);
        $this->plugin = ilObjectPlugin::getPluginObjectByType('lmap');
    }

    /**
     * @return void
     */
    public function executeCommand(): void
    {
        $this->initTabs();

        $cmd = $this->ctrl->getCmd();

        switch ($cmd) {
            case self::TOUR_VIEW:
            case self::TOUR_ADD_ITEM:
            case self::TOUR_DELETE_ITEM:
            case self::TOUR_SAVE_ORDER:
            case self::SHOW_MAP_SETTINGS:
            case self::SAVE_MAP_SETTINGS:
                $this->$cmd();
                break;
        }
    }

    /**
     * @return void
     * @throws ilCtrlException
     */
    private function initTabs(): void
    {
        $this->dic->tabs()->addTab(
            self::TOUR_VIEW,
            $this->dic->language()->txt('content'),
            $this->ctrl->getLinkTarget($this, self::TOUR_VIEW),
        );

        $this->dic->tabs()->addTab(
            self::SHOW_MAP_SETTINGS,
            $this->dic->language()->txt('settings'),
            $this->ctrl->getLinkTarget($this, self::SHOW_MAP_SETTINGS),
        );
    }

    /**
     * @return void
     */
    private function tourView(): void
    {
        $this->dic->tabs()->activateTab(self::TOUR_VIEW);
        $this->tpl->addCss('Customizing/global/plugins/Services/COPage/PageComponent/LearnplacesMap/style/style.css');

        $modal_and_button = $this->tour_view->addItemModal();

        $this->tpl->setContent(
            $this->tour_view->getMap()
            . $this->renderer->render([
                $this->factory->divider()->horizontal(),
                $modal_and_button['modal'],
                $modal_and_button['button'],
                $this->factory->divider()->horizontal(),
                $this->tour_view->getTable(),
        ]));
    }

    /**
     * @return void
     * @throws ilCtrlException
     */
    private function tourSaveOrder(): void
    {
        $table = $this->tour_view->getTable();
        $order = $table->withRequest($this->request)->getData();

        foreach ($order as $new_position => $id) {
            $this->tour_model->updatePosition((int) $id, (int) $new_position);
        }

        $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_SUCCESS, 'Erfolgreich sortiert', true);
        $this->ctrl->redirect($this, self::TOUR_VIEW);
    }

    /**
     * @return void
     * @throws ilCtrlException
     */
    private function tourAddItem(): void
    {
        global $DIC;
        $modal = $this->tour_view->addItemModal()['modal'];

        $form_data = $modal->getForm()->withRequest($this->request)->getData();
        $ref_id = ((int) $form_data['ref_id']) ?? null;
        if (!$ref_id) {
            $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_FAILURE, $this->plugin->txt('nothing_selected'), true);
            $this->ctrl->redirect($this, self::TOUR_VIEW);
        }

        $this->tour_model->addItem($this->map_id, $ref_id);

        $this->ctrl->redirect($this, self::TOUR_VIEW);
    }

    /**
     * @return void
     * @throws ilCtrlException
     */
    private function tourDeleteItem(): void
    {
        $query = $this->http->wrapper()->query();
        if ($query->has('delete_ids')) {
            $ids = $query->retrieve('delete_ids', $this->refinery->kindlyTo()->listOf($this->refinery->kindlyTo()->string()));

            if (($ids[0] ?? null) === 'ALL_OBJECTS') {
                $this->tour_model->deleteAllItems($this->map_id);
            } else {
                $ids = array_map('intval', $ids);
                $this->tour_model->deleteItems($this->map_id, $ids);
            }
            $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_SUCCESS, $this->plugin->txt('deleted'), true);
        } else {
            $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_FAILURE, $this->plugin->txt('nothing_selected'), true);
        }

        $this->ctrl->redirect($this, self::TOUR_VIEW);
    }

    /**
     * @return void
     * @throws ilCtrlException
     */
    private function showMapSettings(): void
    {
        $this->dic->tabs()->activateTab(self::SHOW_MAP_SETTINGS);

        $edit_tour_form = $this->page_component_service->getMapUpdateForm(
            $this->map_id,
            $this->dic->ctrl()->getFormActionByClass(\ilLearnplacesMapTourGUI::class, \ilLearnplacesMapTourGUI::SAVE_MAP_SETTINGS),
        );

        $this->tpl->setContent($this->renderer->render([
            $edit_tour_form,
        ]));
    }

    /**
     * @return void
     * @throws ilCtrlException
     */
    private function saveMapSettings(): void
    {
        $form = $this->page_component_service->getMapUpdateForm(
            -1,
            '#',
        )->withRequest($this->request);
        $form_data = $form->getData();

        if ($form->getError()) {
            $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_FAILURE, $form->getError(), true);
            $this->ctrl->redirect($this, self::SHOW_MAP_SETTINGS);
        }

        $title = $form_data['section']['title'];
        $description = $form_data['section']['description'];

        $this->page_component_service->updateMap($this->map_id, $title, $description);

        $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_SUCCESS, $this->dic->language()->txt('saved_successfully'), true);
        $this->ctrl->redirect($this, self::SHOW_MAP_SETTINGS);
    }
}
