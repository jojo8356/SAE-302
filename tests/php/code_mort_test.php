<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/scripts/code_mort_lib.php';

$source = <<<'PHP'
<?php
class Exemple {
    public function __construct(private string $stocke) {}
    public function methodeUtilisee(string $utilise, int $oublie): string {
        $unique = 3;
        $lu = 4;
        return $utilise . $lu;
    }
    private function fonctionOubliee(): void {}
    public function statique(): void { self::$partage = 1; }
    public function indirect(string $argument): void { echo compact('argument'); }
    public function vue(string $data): void { include 'vue.php'; }
}
$r = [Exemple::class, 'methodeUtilisee'];
PHP;
$alertes = cm_analyser(['app/exemple.php' => $source], ['app/exemple.php']);
$cles = array_column($alertes, 'cle');
$tests = [
    'paramètre inutilisé détecté' => in_array('app/exemple.php|methodeutilisee|paramètre non cité dans le corps|$oublie', $cles, true),
    'variable écrite mais jamais relue détectée' => in_array('app/exemple.php|methodeutilisee|variable écrite une seule fois|$unique', $cles, true),
    'méthode privée sans référence détectée' => in_array('app/exemple.php|fonctionoubliee|fonction ou méthode sans référence textuelle|fonctionoubliee', $cles, true),
    'callback littéral reconnu' => !in_array('app/exemple.php|methodeutilisee|fonction ou méthode sans référence textuelle|methodeutilisee', $cles, true),
    'paramètre utilisé épargné' => !in_array('app/exemple.php|methodeutilisee|paramètre non cité dans le corps|$utilise', $cles, true),
    'variable relue épargnée' => !in_array('app/exemple.php|methodeutilisee|variable écrite une seule fois|$lu', $cles, true),
    'propriété promue conservée' => !in_array('app/exemple.php|__construct|paramètre non cité dans le corps|$stocke', $cles, true),
    'propriété statique non considérée variable locale' => !in_array('app/exemple.php|statique|variable écrite une seule fois|$partage', $cles, true),
    'compact traité prudemment' => !in_array('app/exemple.php|indirect|paramètre non cité dans le corps|$argument', $cles, true),
    'inclusion traitée prudemment' => !in_array('app/exemple.php|vue|paramètre non cité dans le corps|$data', $cles, true),
];
$tests['référentiel bloque un nouveau candidat'] = count(cm_nouvelles($alertes, [])) === count($alertes) && count($alertes) > 0;
$tests['référentiel accepte les candidats déjà identifiés'] = cm_nouvelles($alertes, $cles) === [];
$echecs = 0;
foreach ($tests as $nom => $ok) {
    if ($ok) {
        echo "✅ {$nom}\n";
    } else {
        echo "❌ {$nom}\n";
        ++$echecs;
    }
}
echo 'CODE MORT — autotest : ' . (count($tests) - $echecs) . '/' . count($tests) . "\n";
if ($echecs > 0) {
    exit(1);
}
exit(0);
