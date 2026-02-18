<?php

declare(strict_types=1);

namespace Kpg\Plugins\LearnplacesMap\PageEditor\Collection;

use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\Data\Order;
use ILIAS\UI\Component\Table\DataRowBuilder;
use ILIAS\Data\Range;
use Generator;
use ILIAS\DI\Container;
use KPG\Learnplaces\container\PluginContainer;
use KPG\Learnplaces\persistence\repository\LearnplaceRepository;
use ILIAS\FileUpload\MimeType;

class TableDataRetrieval implements DataRetrieval
{
    public function __construct(
        protected Container $dic,
        protected int $map_id,
    ) {
    }

    /**
     * @param DataRowBuilder $row_builder
     * @param array          $visible_column_ids
     * @param Range          $range
     * @param Order          $order
     * @param array|null     $filter_data
     * @param array|null     $additional_parameters
     * @return Generator
     */
    public function getRows(
        DataRowBuilder $row_builder,
        array $visible_column_ids,
        Range $range,
        Order $order,
        ?array $filter_data,
        ?array $additional_parameters
    ): Generator {
        $tags_of_learnplaces_in_course = $this->getTagsOfLearnplacesInCourse();

        natcasesort($tags_of_learnplaces_in_course);

        $collection_model = new CollectionModel($this->dic);

        foreach ($tags_of_learnplaces_in_course as $tag_name) {
            $record = $collection_model->getGroup($tag_name, $this->map_id);

            $active = $record['active'] ? true : false;
            $color = $record['color'];
            $color_html = "<div style='width: 30px; height: 30px; border-radius: 50%; background-color: $color;'></div>";

            yield $row_builder->buildDataRow($tag_name, [
                'tag_name' => $tag_name,
                'active' => $active,
                'color' => $color_html,
                ]
            );
        }
    }

    /**
     * @param array|null $filter_data
     * @param array|null $additional_parameters
     * @return int|null
     */
    public function getTotalRowCount(?array $filter_data, ?array $additional_parameters): ?int
    {
        return 0;
    }

    /**
     * @return array
     */
    public function getTagsOfLearnplacesInCourse(): array
    {
        $context_ref_id = \ilLearnplacesMapPlugin::getContext();
        $all_learnplaces_in_course = $this->dic->repositoryTree()->getSubTree(
            $this->dic->repositoryTree()->getNodeData($context_ref_id),
            false,
            ['xsrl']
        );

        $all_tags = [];
        foreach ($all_learnplaces_in_course as $learnplace_ref_id) {
            $all_tags[] = $this->getTagsOfLearnplace($learnplace_ref_id);
        }

        return array_unique(array_merge([], ...$all_tags));
    }

    /**
     * @param int $learnplace_ref_id
     * @return array{string}
     */
    public function getTagsOfLearnplace(int $learnplace_ref_id): array
    {
        $obj_learnplace = PluginContainer::resolve(LearnplaceRepository::class)->findByObjectId(\ilObject::_lookupObjId($learnplace_ref_id));
        $learn_place_config = $obj_learnplace->getConfiguration();
        $array_tags = trim($learn_place_config->getTags() ?? '', ',');
        $array_tags = explode(',', $array_tags);
        $array_tags = array_map('trim', $array_tags);
        $array_tags = array_filter($array_tags);
        return $array_tags;
    }
}