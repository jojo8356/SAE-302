<?php

declare(strict_types=1);

namespace App\View\Image;

use InvalidArgumentException;

/** Texte fourni par la vue pour une image qui doit être annoncée. */
final class AlternativeInformative implements AlternativeImage
{
    private readonly string $description;

    /** Valide que le texte alternatif descriptif n'est pas vide. */
    public function __construct(string $description)
    {
        $description = trim($description);
        if ($description === '') {
            throw new InvalidArgumentException('Une image informative exige un texte alternatif non vide.');
        }
        $this->description = $description;
    }

    /** Retourne le texte alternatif validé non vide de l'image informative. */
    public function texte(): string
    {
        return $this->description;
    }
}
