<?php

declare(strict_types=1);

namespace App\View\Image;

/** L'image n'apporte pas d'information supplémentaire au contexte textuel. */
final class AlternativeDecorative implements AlternativeImage
{
    public function texte(): string
    {
        return '';
    }
}
