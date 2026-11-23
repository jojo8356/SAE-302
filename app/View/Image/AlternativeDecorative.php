<?php

declare(strict_types=1);

namespace App\View\Image;

/** L'image n'apporte pas d'information supplémentaire au contexte textuel. */
final class AlternativeDecorative implements AlternativeImage
{
    /** Retourne une chaîne vide pour marquer l'image comme purement décorative. */
    public function texte(): string
    {
        return '';
    }
}
