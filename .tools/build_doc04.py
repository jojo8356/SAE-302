# -*- coding: utf-8 -*-
"""Assemble le livrable n°3 autonome « Conception de la base de données et SQL — MiniShop »
   à partir du CDC §12 (source) et de l'annexe SQL (scripts verbatim)."""
import re, pathlib, base64

UP = pathlib.Path("/home/user/docs")            # documents officiels (.md)
ANN = pathlib.Path("/home/user/docs/annexes")      # annexes (.md)
OUT = pathlib.Path("/home/user/docs")
OUT.mkdir(exist_ok=True)
(OUT / "diagrams").mkdir(exist_ok=True)

cdc = (UP / "01-cahier-des-charges-MiniShop.md").read_text(encoding="utf-8").splitlines()
ann = (ANN / "annexe-sql.md").read_text(encoding="utf-8").splitlines()

# ---------- 1. Extraction du §12 ----------
i_start = next(i for i, l in enumerate(cdc) if l.startswith("## 12. Conception"))
i_end   = next(i for i, l in enumerate(cdc) if l.startswith("## 13. Organisation"))
sec = cdc[i_start:i_end]
# retirer un éventuel "---" final isolé
while sec and sec[-1].strip() in ("", "---"):
    sec.pop()

def find(prefix):
    return next(i for i, l in enumerate(sec) if l.startswith(prefix))

idx = {
    "titre":  find("## 12. Conception"),
    "121":    find("### 12.1 Modèle"),
    "122":    find("### 12.2 Modèle"),
    "123":    find("### 12.3 Normalisation"),
    "124":    find("### 12.4 Script"),
    "125":    find("### 12.5 Procédures"),
    "1251":   find("#### 12.5.1"),
    "1252":   find("### 12.5.2"),
    "126":    find("### 12.6 Déclencheurs"),
    "127":    find("### 12.7 Données"),
    "128":    find("### 12.8 Variantes"),
}
block = {
    "intro":  sec[idx["titre"]+1 : idx["121"]],
    "121":    sec[idx["121"] : idx["122"]],
    "122":    sec[idx["122"] : idx["123"]],
    "123":    sec[idx["123"] : idx["124"]],
    "124":    sec[idx["124"] : idx["125"]],
    "125":    sec[idx["125"] : idx["1251"]],
    "1251":   sec[idx["1251"] : idx["1252"]],
    "1252":   sec[idx["1252"] : idx["126"]],
    "126":    sec[idx["126"] : idx["127"]],
    "127":    sec[idx["127"] : idx["128"]],
    "128":    sec[idx["128"] : len(sec)],
}

def ren(lines, old, new):
    assert lines[0].startswith(old), f"titre inattendu : {lines[0]!r}"
    lines[0] = new + lines[0][len(old):]
    return lines

# ---------- 2. Re-assemblage dans le plan du sujet ----------
out = []

# --- couverture
out += [
"# MiniShop — Conception de la base de données et SQL",
"",
"**Livrable n°3 du sujet : « Document à rendre : Conception de BD et SQL dans un langage de programmation ».**",
"",
"| | |",
"|---|---|",
"| **Projet** | MiniShop — site e-commerce avec back-office |",
"| **Cadre** | SAE — BUT Informatique / Licence 3, Université Côte d'Azur |",
"| **Référente** | Thanh-Phuong Nguyen — `thanh-phuong.nguyen@univ-cotedazur.fr` |",
"| **Barème visé** | 20 points — pénalités couvertes : script SQL manquant (−3), procédure stockée manquante (−1, max −5), déclencheur manquant (−1, max −5), entité MCD manquante (−2), mauvaise cardinalité (−1) |",
"| **Plan imposé par le sujet** | **A)** Analyse de BD : i) MCD · ii) MLD · iii) Normalisation · iv) Script SQL — **B)** Procédures stockées (≥ 5) + question pédagogique — **C)** Triggers (≥ 5) |",
"| **Chiffres clés** | 8 tables + 3 vues · 1 fonction + **17 procédures stockées** (min. 5) · **16 déclencheurs** (min. 5) · 29 tests SQL conformes |",
"| **Documents liés** | `docs/01-cahier-des-charges-MiniShop.md` (spécification) · `docs/02-document-tests-validation.md` · `README.md` |",
"| **Fichiers SQL liés** | `sql/01…04` (schéma, procédures, déclencheurs, démo) · `tests/sql/` (fixture, manifeste) — liens en fin de document |",
"| **Version** | 1.1 — 23 septembre 2026 (scripts livrés en fichiers, plus de recopie verbatim) |",
"",
"---",
"",
"## 0. Conventions de lecture et conformité au plan du sujet",
"",
"### 0.1 Références croisées (sigles utilisés dans ce document)",
"",
"| Référence | Signification | Définie en |",
"|---|---|---|",
"| `RB-01…RB-20` | règles métier | `docs/01` §6 |",
"| `SEC-01…SEC-14` | exigences de sécurité | `docs/01` §8 |",
"| `CT-01…CT-09` | contraintes techniques | `docs/01` §7 |",
"| `ENF-01…ENF-19` | exigences non fonctionnelles | `docs/01` annexe 17.3 (`docs/annexes/annexe-enf.md`) |",
"| `UC-01…UC-14` | cas d'utilisation | `docs/01` §5.1 + annexe 17.1 |",
"| `T-xx` / `F-xx` / `S-xx` / `EF-xxx` | cas de test (règles / fonctionnels / sécurité) | `docs/02` |",
"| `V1…V7` | variantes de conception (arbitrages) | **partie D du présent document** |",
"| `A.x` / `B.x` / `C.x` | sections du présent document | — |",
"",
"### 0.2 Table de conformité — chaque exigence du point 3 du sujet et où elle est traitée",
"",
"> Plan imposé par le sujet (point 3) : **A) Analyse de BD** → i) MCD, ii) MLD, iii) Normalisation, iv) Script SQL ; **B) Procédures stockées** (+ question pédagogique) ; **C) Triggers**. Les parties suivent ce plan à l'identique, avec deux ajouts demandés implicitement : les cardinalités justifiées (i) et le jeu de données (iv).",
"",
"| Exigence du sujet | Traité en | Pénalité couverte |",
"|---|---|---|",
"| i) MCD — entités, associations, **cardinalités justifiées** | A.1 (figure + justification ligne à ligne) | entité manquante −2 · cardinalité erronée −1 |",
"| ii) MLD — transformation en relations | A.2 (8 relations + 3 vues) | — |",
"| iii) Normalisation — DF, 1FN/2FN/3FN, clés primaires **et** étrangères justifiées, redondances | A.3.1 → A.3.4 (+ preuve exécutable A.3.5) | — |",
"| iv) Script SQL **avec insertion de données** | A.4 + [`sql/01_minishop_schema.sql`](../sql/01_minishop_schema.sql) (DDL + données) + [`sql/04_minishop_demo.sql`](../sql/04_minishop_demo.sql) (jeu créé par `CALL`) | script SQL manquant −3 |",
"| B) ≥ 5 procédures stockées (ex. `sp_create_order`, `sp_add_order_item`, `sp_update_order_status`) | B.1 : **17 livrées + 1 fonction** ; correspondance nom à nom avec les exemples du sujet en B.3 | procédure manquante −1 (max −5) |",
"| Question pédagogique (centralisation, sécurité, transactions, réutilisabilité, contrôle des accès) | B.2 (table aspect par aspect, limites comprises) | — |",
"| C) ≥ 5 triggers (ex. « Trigger 1 — Stock », « Trigger 2 — Historique » sur `ORDER_STATUS_HISTORY`) | C.1 : **16 livrés** ; couverture explicite des deux triggers imposés en C.2 | déclencheur manquant −1 (max −5) |",
"",
"---",
"",
]

# --- Partie A
out += ["## Partie A — Analyse de la base de données (i → iv du sujet)", ""]
out += ren(block["121"][:], "### 12.1 ", "### A.1 ")
out.append("")
out += ren(block["122"][:], "### 12.2 ", "### A.2 ")
out.append("")
out += ren(block["123"][:], "### 12.3 ", "### A.3 ")
out.append("")
out += ren(block["124"][:], "### 12.4 ", "### A.4 ")
out.append("")
out += ren(block["127"][:], "### 12.7 ", "### A.5 ")
out.append("")
out.append("---")
out.append("")

# --- Partie B
out += ["## Partie B — Procédures stockées", ""]
out += ren(block["125"][:], "### 12.5 ", "### B.1 ")
out.append("")
out += ren(block["1251"][:], "#### 12.5.1 ", "### B.2 ")
out.append("")
out += ren(block["1252"][:], "### 12.5.2 ", "### B.3 ")
out.append("")
out.append("---")
out.append("")

# --- Partie C
c = ren(block["126"][:], "### 12.6 ", "## Partie C — ")
# insérer C.1 juste après le titre
c.insert(1, "")
c.insert(2, "### C.1 Catalogue des 16 déclencheurs")
body = []
for l in c:
    if l.startswith("L'exigence « Trigger 1 — Stock » est couverte"):
        body += ["### C.2 Couverture des deux triggers imposés par le sujet", ""]
    if l.startswith("**Ce que les triggers prouvent dans les tests** :"):
        body += ["### C.3 Preuves par les tests SQL directs", ""]
        l = l.replace("**Ce que les triggers prouvent dans les tests** : ", "Les tests ")
    body.append(l)
out += body
out.append("")
out.append("---")
out.append("")

# --- Partie D
out += ["## Partie D — Variantes de conception documentées (arbitrages)", ""]
out += block["128"][:]
out.append("")
out.append("---")
out.append("")

# ---------- 3. Références croisées internes (§12.x → A/B/C/D) ----------
doc = "\n".join(out)
doc = doc.replace("(V. §12.3.2 `PRODUIT`)", "(cf. A.3.2 `PRODUIT`)")
doc = doc.replace("du §12.3.5", "de la section A.3.5")
doc = doc.replace("le §12.3 refuse", "la section A.3 refuse")
doc = doc.replace("(§12.6)", "(partie C)")
doc = doc.replace("(§12.7)", "(A.5)")
for old, new in [("§12.3.5", "A.3.5"), ("§12.3.4", "A.3.4"), ("§12.3.2", "A.3.2"),
                 ("§12.3.1", "A.3.1"), ("§12.3", "A.3"), ("§12.2", "A.2"),
                 ("§12.4", "A.4"), ("§12.1.3", "A.1.3")]:
    doc = doc.replace(old, new)
doc = doc.replace("en annexe 17.4", "dans le fichier `sql/01_minishop_schema.sql`, livré à la racine du dépôt")
# chemins d'images : le document vit dans docs/, les images dans docs/diagrams/
doc = doc.replace("](docs/diagrams/", "](diagrams/")

# ---------- 4. Annexes : liens vers les fichiers livrés ----------
import shutil
SQLDIR = pathlib.Path("/home/user/sql")

def nlines(p): return len(p.read_text(encoding="utf-8").splitlines())

fichiers = [
 ("[`sql/01_minishop_schema.sql`](../sql/01_minishop_schema.sql)",
  "DDL : 8 tables, 3 vues, index FULLTEXT + jeu de données de référence (4 catégories, 12 produits, 3 clients, 1 admin, hashes `password_hash()`, table `parametre`)",
  "A.1 · A.2 · A.4 · A.5", nlines(SQLDIR/"01_minishop_schema.sql")),
 ("[`sql/02_minishop_procedures.sql`](../sql/02_minishop_procedures.sql)",
  "directive `DELIMITER` + 1 fonction (`fn_param`) + 17 procédures stockées",
  "B.1 · B.2 · B.3", nlines(SQLDIR/"02_minishop_procedures.sql")),
 ("[`sql/03_minishop_triggers.sql`](../sql/03_minishop_triggers.sql)",
  "directive `DELIMITER` + 16 déclencheurs (blocs A stock, B prix/immuabilité, C catégories, D commandes/historique)",
  "C.1 · C.2 · C.3", nlines(SQLDIR/"03_minishop_triggers.sql")),
 ("[`sql/04_minishop_demo.sql`](../sql/04_minishop_demo.sql)",
  "4 commandes de démonstration créées **par `CALL` des procédures** (déclencheurs réellement exécutés)",
  "A.5", nlines(SQLDIR/"04_minishop_demo.sql")),
 ("[`tests/sql/fixture.sql`](../tests/sql/fixture.sql)",
  "jeu de données des tests (état de référence mémorisé dans `stock_initial`)",
  "C.3 · doc 02", nlines(pathlib.Path("/home/user/tests/sql/fixture.sql"))),
 ("[`tests/sql/manifest.txt`](../tests/sql/manifest.txt)",
  "manifeste des 29 tests SQL (résultat attendu par test)",
  "C.3 · doc 02", nlines(pathlib.Path("/home/user/tests/sql/manifest.txt"))),
 ("[`tests/perf/mesurer.sh`](../tests/perf/mesurer.sh)",
  "protocole de mesure p95 HTTP (`ENF-01`)",
  "doc 02 §5", nlines(pathlib.Path("/home/user/tests/perf/mesurer.sh"))),
 ("[`tests/perf/mesurer_sql.sh`](../tests/perf/mesurer_sql.sh)",
  "mesure côté base : plans d'exécution, p95, coût d'une écriture métier",
  "A.3 · doc 02 §5", nlines(pathlib.Path("/home/user/tests/perf/mesurer_sql.sh"))),
 ("[`scripts/gen_volumes.sh`](../scripts/gen_volumes.sh)",
  "générateur de volumétrie (200 produits / 1 000 commandes via les procédures)",
  "doc 02 §5", nlines(pathlib.Path("/home/user/scripts/gen_volumes.sh"))),
 ("[`.gitlab-ci.yml`](../.gitlab-ci.yml)",
  "chaîne CI : 6 jobs alignés sur le barème (lint, sécurité, tests SQL, unitaires, diagrammes) ; "
  "**pas de re-run après un merge dont la MR était verte**",
  "CDC §14.2", nlines(pathlib.Path("/home/user/.gitlab-ci.yml"))),
 ("[`.gitignore`](../.gitignore)",
  "secrets hors dépôt (`app/Config/env.php`), artefacts régénérables",
  "CDC §14.1 règle 7", nlines(pathlib.Path("/home/user/.gitignore"))),
]

annex_lines = []
annex_lines.append("")
annex_lines.append("## Annexes — les scripts sont livrés en fichiers (liens)")
annex_lines.append("")
annex_lines.append("Depuis la version 1.1, les scripts SQL ne sont plus recopiés dans ce document : ils sont livrés comme")
annex_lines.append("**fichiers réels du dépôt** (une seule source de vérité, versionnée, différenciable et rejouable).")
annex_lines.append("Le tableau relie chaque fichier aux parties du présent document qui l'analysent.")
annex_lines.append("")
annex_lines.append("| Fichier | Contenu | Rattaché à | Lignes |")
annex_lines.append("|---|---|---|---:|")
for lien, contenu, rattache, n in fichiers:
    annex_lines.append(f"| {lien} | {contenu} | {rattache} | {n} |")
annex_lines.append("")
annex_lines.append("**Rejeu complet** (le 01 crée la base et fait `USE` ; 02 et 03 contiennent la directive `DELIMITER` ;")
annex_lines.append("03 et 04 supposent les objets des étapes précédentes) :")
annex_lines.append("")
annex_lines.append("```bash")
annex_lines.append("mysql -u root -p          < sql/01_minishop_schema.sql")
annex_lines.append("mysql -u root -p minishop < sql/02_minishop_procedures.sql")
annex_lines.append("mysql -u root -p minishop < sql/03_minishop_triggers.sql")
annex_lines.append("mysql -u root -p minishop < sql/04_minishop_demo.sql")
annex_lines.append("```")
annex_lines.append("")
annex_lines.append("**Validation exécutée (23/09/2026, MariaDB 11.8.6)** — chaîne rejouée de zéro : 8 tables, 3 vues,")
annex_lines.append("1 fonction, **17 procédures** et **16 déclencheurs** chargés ; les 4 commandes de démonstration retrouvées")
annex_lines.append("avec leurs montants exacts (324,60 / 0,00 / 405,48 / 63,70 EUR dont 4,90 de port) et les 9 traces")
annex_lines.append("d'historique ; les gardes RB-01/02/03/05/06/09/11/14/15/18 vérifiés **en SQL direct** (rejets attendus) ;")
annex_lines.append("rollback intégral du panier confirmé (stock inchangé, aucune commande créée) ; les 4 hashes du seed")
annex_lines.append("reconnus comme sorties réelles de `password_hash()` (bcrypt) pour les mots de passe de démonstration.")
annex_lines.append("")
annex_lines.append("**Corrections v1.1 apportées aux scripts** (blocantes au chargement direct, détectées par ce rejeu) :")
annex_lines.append("(i) ajout des directives `DELIMITER $$ … DELIMITER ;` dans `sql/02` et `sql/03`, promises par l'en-tête")
annex_lines.append("du 01 mais absentes — `mysql < sql/02…` échouait sinon ; (ii) le déclencheur `trg_history_creation`")
annex_lines.append("(TRG-D5) se terminait par `;` au lieu de `$$` : il n'était jamais créé (15/16 déclencheurs seulement,")
annex_lines.append("et aucune trace `NULL → BROUILLON`) ; (iii) commentaire du script 04 corrigé (produit 8 = sac à dos")
annex_lines.append("SAC-008, 58,80 EUR — pas l'écran) ; (iv) en-tête du 01 : commande de chargement complétée (fichier 04,")
annex_lines.append("nom de base pour 02→04) et bloc `parametre` replacé avant la table `commande` ; (v) référence")
annex_lines.append("`Ecran-27-003` normalisée en `ECRA-003` (convention de nommage). Note MySQL/MariaDB : `CAST(… AS JSON)`")
annex_lines.append("est du pur MySQL — sous MariaDB (et donc en PDO), passer le panier **directement comme chaîne** au")
annex_lines.append("paramètre déclaré `JSON` (alias `LONGTEXT`).")
annex_lines.append("")

doc += "\n".join(annex_lines)

# garde-fou : plus aucune référence résiduelle « §12 » dans le corps
corps = doc.split("## Annexes — les scripts sont livrés en fichiers", 1)[0]
restants = re.findall(r"§12[0-9.]*", corps)
assert not restants, f"références résiduelles : {restants}"

path_md = OUT / "04-conception-bd-et-sql.md"
path_md.write_text(doc, encoding="utf-8")
print(f"OK markdown : {path_md} ({len(doc.splitlines())} lignes, {path_md.stat().st_size/1024:.0f} Ko)")

# ---------- 5. Diagrammes (déjà en place dans docs/diagrams/) ----------
for img in ["mcd_minishop.png", "mld_minishop.png"]:
    assert (OUT / "diagrams" / img).exists(), f"schéma manquant : {img}"
print("OK diagrammes présents dans docs/diagrams/")
