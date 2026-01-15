<?php

declare(strict_types=1);

use ILIAS\DI\Container;
use Kpg\Plugins\LearnplacesMap\PageEditor\Collection\CollectionView;
use Kpg\Plugins\LearnplacesMap\PageEditor\Collection\CollectionModel;
use Kpg\Plugins\LearnplacesMap\PageEditor\PageComponent\PageComponentService;
use JetBrains\PhpStorm\NoReturn;
use Kpg\Plugins\LearnplacesMap\PageEditor\Collection\CollectionMapView;

/**
 * @ilCtrl_IsCalledBy ilLearnplacesMapCollectionGUI: ilLearnplacesMapPluginGUI
 */
class ilLearnplacesMapCollectionGUI
{
    public const COLLECTION_VIEW = 'collectionView';
    public const COLLECTION_EDIT_GROUP_MODAL = 'collectionEditGroupModal';
    public const COLLECTION_SAVE_GROUP = 'collectionSaveGroup';
    public const SHOW_MAP_SETTINGS = 'showMapSettings';
    public const SAVE_MAP_SETTINGS = 'saveMapSettings';

    private ilCtrlInterface $ctrl;
    private \ILIAS\UI\Factory $factory;
    private \ILIAS\UI\Renderer $renderer;
    private ilGlobalTemplateInterface $tpl;
    private Container $dic;
    private CollectionView $collection_view;
    private CollectionModel $collection_model;
    private PageComponentService $page_component_service;
    private int $map_id;
    private \Psr\Http\Message\ServerRequestInterface|\Psr\Http\Message\RequestInterface $request;

    public function __construct(
        protected ilLearnplacesMapPluginGUI $parent_gui,
    )
    {
        global $DIC;
        $this->dic = $DIC;
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->ctrl = $DIC->ctrl();
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->request = $DIC->http()->request();
        $this->map_id = (int) $this->parent_gui->getProperties()['id'];
        $this->page_component_service = new PageComponentService($this->dic, $this->factory);
        $this->collection_view = new CollectionView($this->dic, $this->factory, $this->map_id);
        $this->collection_model = new CollectionModel($this->dic);
    }

    public function executeCommand(): void
    {
        $this->initTabs();

        $cmd = $this->ctrl->getCmd();

        switch ($cmd) {
            case self::COLLECTION_VIEW:
            case self::COLLECTION_EDIT_GROUP_MODAL:
            case self::COLLECTION_SAVE_GROUP:
            case self::SHOW_MAP_SETTINGS:
            case self::SAVE_MAP_SETTINGS:
                $this->$cmd();
                break;
        }
    }

    private function initTabs(): void
    {
        $this->dic->tabs()->addTab(
            self::COLLECTION_VIEW,
            $this->dic->language()->txt('content'),
            $this->ctrl->getLinkTarget($this, self::COLLECTION_VIEW),
        );

        $this->dic->tabs()->addTab(
        self::SHOW_MAP_SETTINGS,
            $this->dic->language()->txt('settings'),
            $this->ctrl->getLinkTarget($this, self::SHOW_MAP_SETTINGS),
        );
    }

    private function collectionView(): void
    {
        $this->dic->tabs()->activateTab(self::COLLECTION_VIEW);
        $this->tpl->addCss('Customizing/global/plugins/Services/COPage/PageComponent/LearnplacesMap/style/style.css');

        $this->tpl->setContent(
            $this->collection_view->getMap()
            . $this->renderer->render($this->collection_view->getTable())
        );
    }

    /**
     * Async modal endpoint
     */
    #[NoReturn]
    private function collectionEditGroupModal(): void
    {
        $modal = $this->collection_view->getEditModal();
        exit($this->renderer->renderAsync($modal));
    }

    private function collectionSaveGroup(): void
    {
        $modal = $this->collection_view->getEditModal()->withRequest($this->request);
        $form_data = $modal->getData();

        $tag_name = $form_data['tag_name'];
        $active = $form_data['active'];
        $color = $form_data['color_input']->asHex();

        $this->collection_model->storeGroup($this->map_id, $tag_name, $active, $color);

        $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_SUCCESS, 'Erfolgreich aktualisiert', true);
        $this->ctrl->redirect($this, self::COLLECTION_VIEW);
    }

    private function showMapSettings(): void
    {
        $this->dic->tabs()->activateTab(self::SHOW_MAP_SETTINGS);

        $edit_collection_form = $this->page_component_service->getMapUpdateForm(
            $this->map_id,
            $this->dic->ctrl()->getFormActionByClass(\ilLearnplacesMapCollectionGUI::class, \ilLearnplacesMapCollectionGUI::SAVE_MAP_SETTINGS),
        );

        $this->tpl->setContent($this->renderer->render([
            $edit_collection_form,
        ]));
    }

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

        $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_SUCCESS, 'Erfolgreich aktualisiert', true);
        $this->ctrl->redirect($this, self::SHOW_MAP_SETTINGS);
    }
}
