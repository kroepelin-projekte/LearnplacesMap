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
    ) {
    }

    public function getChildren($record, $environment = null): array
    {
        return $this->dic->repositoryTree()->getChilds((int) $record['ref_id']);
    }

    public function build(Node\Factory $factory, $record, $environment = null): Node\Node
    {
        $ref_id = $record['ref_id'];
        $label = '<input type="radio" id="' . $ref_id . '" name="learnplace" value="' . $ref_id . '" style="margin-right: 5px;">';
        $label .= '<label for="' . $ref_id . '">' . $record['title'] . ' (' . $record['type'] . ', ' . $ref_id . ')</label>';
        // $icon = $environment['icon_factory']->standard($record["type"], '');

        /** @var \ILIAS\UI\Implementation\Component\Tree\Node\Node $node */
        $node = $factory->simple($label, null)->withExpanded(true);

        return $node;
    }
}