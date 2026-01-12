<?php

declare(strict_types=1);

namespace Kpg\Plugins\LearnplacesMap\PageEditor\Collection;

use ILIAS\UI\Component\Input\Container\Form\Standard;
use ILIAS\UI\Factory;
use ILIAS\Data\URI;
use ILIAS\DI\Container;
use ILIAS\UI\Component\Tree\Tree;
use ILIAS\UI\URLBuilder;
use KPG\Learnplaces\container\PluginContainer;
use KPG\Learnplaces\persistence\repository\LearnplaceRepository;
use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\UI\Component\Table\DataRowBuilder;
use ILIAS\Data\Range;
use ILIAS\Data\Order;
use ilLearnplacesMapCollectionGUI;
use ILIAS\UI\Component\Modal\Modal;
use ILIAS\UI\Component\Table\Table;
use ILIAS\FileUpload\MimeType;

class CollectionView
{
    public function __construct(
        protected Container $dic,
        protected Factory $factory,
    ) {
    }

    public function getTable(): Table
    {
        // edit single action
        $url = $this->dic->ctrl()->getLinkTargetByClass(ilLearnplacesMapCollectionGUI::class, ilLearnplacesMapCollectionGUI::COLLECTION_EDIT_GROUP_MODAL);
        $url_builder = new URLBuilder(new URI(ILIAS_HTTP_PATH . '/' . $url));
        list($url_builder, $action_parameter_token, $row_id_token) = $url_builder->acquireParameters(
            ['collection'],
            'learnplaces_map',
            'tag_name',
        );
        $actions['edit'] = $this->factory->table()->action()->single(
            $this->dic->language()->txt('edit'),
            $url_builder,
            $row_id_token,
        )->withAsync(true);

        return $this->factory->table()->data(
            '',
            [
                'tag_name' => $this->factory->table()->column()->text('Tag Name')->withIsSortable(false),
                'active' => $this->factory->table()->column()->boolean(
                    'Aktiv', $this->factory->symbol()->glyph()->apply(),
                    '',
                )->withIsSortable(false),
                'color' => $this->factory->table()->column()->text('Farbe')->withIsSortable(false),
            ],
            new TableDataRetrieval($this->dic),
        )
            ->withActions($actions)
            ->withRequest($this->dic->http()->request());
    }

    public function getEditModal(): Modal
    {
        $query = $this->dic->http()->wrapper()->query();

        $tag_name = '';
        if ($query->has('collection_tag_name')) {
            $tag_name = current($query->retrieve('collection_tag_name', $this->dic->refinery()->kindlyTo()->listOf($this->dic->refinery()->kindlyTo()->string())));
        }

        $collection_model = new CollectionModel($this->dic);
        $record = $collection_model->getGroup($tag_name);
        $type = 'color_radio';
        $active = (bool) $record['active'];
        $color = $record['color'];

        return $this->factory->modal()->roundtrip(
            'Edit Group',
            null,
            [
                'tag_name' => $this->factory->input()->field()->hidden()->withValue($tag_name),
                'active' => $this->factory->input()->field()->checkbox('Aktiv')->withValue($active),
                'color_input' => $this->factory->input()->field()->colorPicker('')->withValue($color)
            ],
            $this->dic->ctrl()->getFormActionByClass(\ilLearnplacesMapCollectionGUI::class, \ilLearnplacesMapCollectionGUI::COLLECTION_SAVE_GROUP, '', true),
        )->withCancelButtonLabel($this->dic->language()->txt('close'));
    }
}