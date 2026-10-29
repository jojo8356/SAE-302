<?php

declare(strict_types=1);

namespace App\View\Image;

/** Texte alternatif brut ; l'échappement HTML est assuré par le rendu. */
interface AlternativeImage
{
    public function texte(): string;
}
