<?php

declare(strict_types=1);

namespace App\View\Image;

/** Texte alternatif brut ; l'échappement HTML est assuré par le rendu. */
interface AlternativeImage
{
    /** Retourne le texte alternatif brut destiné au gabarit de rendu. */
    public function texte(): string;
}
