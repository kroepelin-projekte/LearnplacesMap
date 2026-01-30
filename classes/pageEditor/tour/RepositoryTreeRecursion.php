<?php

declare(strict_types=1);

namespace Kpg\Plugins\LearnplacesMap\PageEditor\Tour;

use ILIAS\UI\Component\Tree\TreeRecursion;
use ILIAS\UI\Component\Tree\Node;
use ILIAS\DI\Container;

class RepositoryTreeRecursion implements TreeRecursion
{
    public function __construct(
        protected Container $dic,
        protected TourModel $tour_model,
    ) {
    }

    /**
     * @param $record
     * @param $environment
     * @return array
     */
    public function getChildren($record, $environment = null): array
    {
        $ref_id = (int) $record['ref_id'];

        $children = $this->dic->repositoryTree()->getChildsByTypeFilter($ref_id, ['xsrl', 'cat', 'fold']);

        if (in_array($record['type'], ['fold', 'cat', 'crs'])) {
            $children = $this->tour_model->sortObjects(\ilObject::_lookupObjectId($ref_id), $children);
        }

        return $children;
    }

    /**
     * @param Node\Factory $factory
     * @param              $record
     * @param              $environment
     * @return Node\Node
     */
    public function build(Node\Factory $factory, $record, $environment = null): Node\Node
    {
        $ref_id = $record['ref_id'];
        $type = $record['type'];
        $disabled = $environment['tour_model']->itemExists($environment['map_id'], $ref_id) ? 'disabled' : '';

        if ($type === 'xsrl') {
            $label = '<input type="radio" ' . $disabled . ' id="' . $ref_id . '" name="learnplace" value="' . $ref_id . '" style="margin-right: 5px;">';
            $label .= '<label for="' . $ref_id . '">' . $record['title'] . ' (' . $record['type'] . ', ' . $ref_id . ')</label>';
        } else {
            $label = $record['title'] . ' (' . $record['type'] . ', ' . $ref_id . ')';
        }

        return $factory->simple($label)->withExpanded(true);
    }
}
