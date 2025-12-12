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
        $label = '<input type="radio" name="source_ids[]" value="' . $ref_id . '" style="margin-right: 5px;">';
        $label .= $record['title'] . ' (' . $record['type'] . ', ' . $ref_id . ')';
        $icon = $environment['icon_factory']->standard($record["type"], '');

        /** @var \ILIAS\UI\Implementation\Component\Tree\Node\Node $node */
        $node = $factory->simple($label, $icon)->withExpanded(true);

        return $node;
    }
}