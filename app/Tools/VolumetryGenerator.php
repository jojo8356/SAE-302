<?php
declare(strict_types=1);

namespace App\Tools;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * MiniShop — VolumetryGenerator
 *
 * Logique métier du générateur de volumétrie, extraite pour être réutilisable
 * depuis un contrôleur MVC ou depuis le script standalone public/gen_volumes.php.
 * Le script public reste autonome (pas d'autoload obligatoire) mais peut, s'il
 * le souhaite, déléguer à cette classe quand l'autoloader est présent.
 *
 * - Produits : INSERT ... VALUES par lot (jamais INSERT ... SELECT → trigger prix_ttc)
 * - Clients  : INSERT IGNORE perf%@minishop.local
 * - Commandes : CALL sp_create_order / sp_add_order_line ×2 / sp_confirm_order
 * - Purge    : DELETE VP-*, perf%@, id_commande > 100
 */
final class VolumetryGenerator
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array<string,int> */
    public function currentCounts(): array
    {
        $sql = "SELECT
            (SELECT COUNT(*) FROM produit) AS produits,
            (SELECT COUNT(*) FROM client) AS clients,
            (SELECT COUNT(*) FROM commande) AS commandes,
            (SELECT COUNT(*) FROM ligne_commande) AS lignes,
            (SELECT COUNT(*) FROM order_status_history) AS traces,
            (SELECT COUNT(*) FROM commande WHERE frais_port > 0) AS commandes_avec_port,
            (SELECT COUNT(*) FROM produit WHERE prix_ttc <> ROUND(prix_ht*(1+tva/100),2)) AS ttc_incoherents,
            (SELECT COUNT(*) FROM commande c WHERE c.statut <> 'BROUILLON' AND c.montant_total <>
                (SELECT COALESCE(SUM(l.total_ligne),0) FROM ligne_commande l WHERE l.id_commande=c.id_commande) + c.frais_port) AS montants_incoherents";
        $row = $this->pdo->query($sql)->fetch();
        return $row ?: [];
    }

    /** @return array<string,int> */
    public function purge(): array
    {
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $deleted = [];
        $deleted['lignes']     = (int)$this->pdo->exec("DELETE FROM ligne_commande WHERE id_commande > 100");
        $deleted['traces']     = (int)$this->pdo->exec("DELETE FROM order_status_history WHERE order_id > 100");
        $deleted['commandes']  = (int)$this->pdo->exec("DELETE FROM commande WHERE id_commande > 100");
        $deleted['produits']   = (int)$this->pdo->exec("DELETE FROM produit WHERE reference LIKE 'VP-%'");
        $deleted['clients']    = (int)$this->pdo->exec("DELETE FROM client WHERE email LIKE 'perf%@minishop.local'");
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        return $deleted;
    }

    /** @param string[] $log */
    public function generateProduits(int $n, array &$log): int
    {
        if ($n <= 0) return 0;
        $batchSize = 100;
        $prefix = "INSERT INTO produit (reference, nom, slug, description, prix_ht, tva, stock, seuil_alerte, id_categorie, visible) VALUES ";
        $inserted = 0;
        for ($off = 0; $off < $n; $off += $batchSize) {
            $end = min($off + $batchSize, $n);
            $values = []; $params = [];
            for ($i = $off + 1; $i <= $end; $i++) {
                $prix = 5 + ($i * 37 % 900) + ($i % 10) / 10;
                $prixStr = number_format($prix, 2, '.', '');
                $stk = 20 + ($i * 7 % 180);
                $cid = 1 + ($i % 4);
                $ref = sprintf('VP-%04d', $i);
                $values[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                array_push($params, $ref, "Produit de mesure $i", "produit-de-mesure-$i",
                    "Genere par VolumetryGenerator pour ENF-01 : description de taille moyenne, prix variable.",
                    $prixStr, '20.00', $stk, 5, $cid, 1);
            }
            $stmt = $this->pdo->prepare($prefix . implode(', ', $values));
            $stmt->execute($params);
            $inserted += $stmt->rowCount();
            $log[] = "  produits $end / $n insérés";
        }
        return $inserted;
    }

    /** @param string[] $log */
    public function generateClients(int $n, array &$log): int
    {
        if ($n <= 0) return 0;
        $hash = $this->pdo->query("SELECT mot_de_passe_hash FROM client WHERE id_client=1")->fetchColumn();
        if (!$hash) $hash = '$2y$12$dummyhashdummyhashdummyhashdummyha';
        $sql = "INSERT IGNORE INTO client (nom, prenom, email, mot_de_passe_hash, adresse_livraison, code_postal, ville, actif) VALUES ";
        $values = []; $params = [];
        for ($i = 1; $i <= $n; $i++) {
            $values[] = "(?, ?, ?, ?, ?, ?, ?, ?)";
            array_push($params, "Citoyen-$i", 'Mesure', "perf{$i}@minishop.local", $hash, "$i rue du Benchmark", '06000', 'Nice', 1);
        }
        $stmt = $this->pdo->prepare($sql . implode(', ', $values));
        $stmt->execute($params);
        $log[] = "  clients : $n demandés, " . $stmt->rowCount() . " nouveaux (IGNORE si déjà présent)";
        return $stmt->rowCount();
    }

    /**
     * @param string[] $log
     * @return array{ok:int,errors:int}
     */
    public function generateCommandes(int $nCommandes, int $batch, array &$log): array
    {
        if ($nCommandes <= 0) return ['ok'=>0,'errors'=>0];
        $prodIds = $this->pdo->query("SELECT id_produit FROM produit ORDER BY id_produit")->fetchAll(PDO::FETCH_COLUMN);
        $clientIds = $this->pdo->query("SELECT id_client FROM client ORDER BY id_client")->fetchAll(PDO::FETCH_COLUMN);
        if (!$prodIds || !$clientIds) throw new RuntimeException("Aucun produit/client — chargez sql/01+04.");
        $nProd = count($prodIds); $nCli = count($clientIds);
        $t0 = microtime(true); $ok = 0; $errors = 0;

        $stCreate = $this->pdo->prepare("CALL sp_create_order(?, ?, @c, @num)");
        $stL1     = $this->pdo->prepare("CALL sp_add_order_line(@c, ?, ?, @l, @t, @r)");
        $stL2     = $this->pdo->prepare("CALL sp_add_order_line(@c, ?, ?, @l2, @t2, @r2)");
        $stConf   = $this->pdo->prepare("CALL sp_confirm_order(@c, ?, ?, @m, @code)");

        for ($j=0; $j<$nCommandes; $j++) {
            $cid = $clientIds[$j % $nCli];
            $pid1 = $prodIds[$j % $nProd];
            $pid2 = $prodIds[($j*7+3) % $nProd];
            $q1 = 1 + ($j % 3);
            $payee = ($j % 4 === 0) ? 1 : 0;
            $adr = "Adresse de mesure $j, 06000 Nice";
            try {
                $stCreate->execute([$cid, $adr]); $stCreate->closeCursor();
                $c = $this->pdo->query("SELECT @c AS c")->fetchColumn();
                if (!$c) { $errors++; $log[]="  [j=$j] sp_create_order échoué"; continue; }

                $stL1->execute([$pid1, $q1]); $stL1->closeCursor();
                $r1 = $this->pdo->query("SELECT @r AS r")->fetchColumn();
                if ($r1 !== 'OK' && $r1 !== null) $log[]="  [j=$j] ligne1: $r1";

                $stL2->execute([$pid2, 1]); $stL2->closeCursor();
                $r2 = $this->pdo->query("SELECT @r2 AS r")->fetchColumn();
                if ($r2 !== 'OK' && $r2 !== null) $log[]="  [j=$j] ligne2: $r2";

                $stConf->execute([$cid, $payee]); $stConf->closeCursor();
                $code = $this->pdo->query("SELECT @code AS code")->fetchColumn();
                if ($code !== 'OK') { $errors++; $log[]="  [j=$j] confirm: $code"; } else $ok++;
            } catch (PDOException $e) {
                try{$stCreate->closeCursor();}catch(Throwable $t){}
                try{$stL1->closeCursor();}catch(Throwable $t){}
                try{$stL2->closeCursor();}catch(Throwable $t){}
                try{$stConf->closeCursor();}catch(Throwable $t){}
                $log[]="  [j=$j] exception: ".substr($e->getMessage(),0,180);
                $errors++;
            }
            if ((($j+1)%$batch===0) || $j+1===$nCommandes) {
                $log[]=sprintf("  %d / %d commandes — %d s", $j+1, $nCommandes, (int)(microtime(true)-$t0));
            }
        }
        return ['ok'=>$ok,'errors'=>$errors];
    }
}
