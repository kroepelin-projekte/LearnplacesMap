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
    ) {
    }

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
            $record = $collection_model->getGroup($tag_name);

            $active = $record['active'] ? true : false;
            $color = $record['color'];
            $color_html = "<div style='width: 30px; height: 30px; border-radius: 50%; background-color: {$color};'></div>";

            yield $row_builder->buildDataRow($tag_name, [
                'tag_name' => $tag_name,
                'active' => $active,
                'color' => $color_html,
                ]
            );
        }
    }

    public function getTotalRowCount(?array $filter_data, ?array $additional_parameters): ?int
    {
        return 0;
    }

    public function getTagsOfLearnplacesInCourse(): array
    {
        $course_ref_id = \ilLearnplacesMapPlugin::getContext();
        $all_learnplaces_in_course = $this->dic->repositoryTree()->getSubTree(
            $this->dic->repositoryTree()->getNodeData($course_ref_id),
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
        $array_tags = trim($learn_place_config->getTags(), ',');
        $array_tags = explode(',', $array_tags);
        $array_tags = array_map('trim', $array_tags);
        $array_tags = array_filter($array_tags);
        return $array_tags;
    }
}