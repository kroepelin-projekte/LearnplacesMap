<?php

declare(strict_types=1);

namespace Kpg\Plugins\LearnplacesMap\PageEditor\Mode;

use ILIAS\UI\Component\Input\Container\Form\Standard;
use ILIAS\UI\Factory;

class ModeService
{
    public const MODE_TOUR = 'tour';
    public const MODE_COLLECTION = 'collection';

    public function __construct(
        protected Factory $factory
    ) {
    }

    public function getModeForm(string $form_action): Standard
    {
        $mode_radio_input = $this->factory->input()->field()->radio('Mode', '')
            ->withOption(self::MODE_TOUR, 'Tour')
            ->withOption(self::MODE_COLLECTION, 'Sammlung')
            ->withRequired(true);

        $section = $this->factory->input()->field()->section(
            [
                'mode' => $mode_radio_input,
            ],
            'Karten Typ'
        );

        return $this->factory->input()->container()->form()->standard(
            $form_action,
            [
                'section' => $section,
            ],
        );
    }
}