<?php

namespace App\Core;

use RuntimeException;

/**
 * Moteur de templates minimal, compilé en PHP puis mis en cache.
 *
 * Syntaxe :
 *   {{ expr }}                 affichage échappé (par défaut)
 *   {! expr !}                 affichage brut, volontairement plus voyant
 *   {% if expr %} {% elseif expr %} {% else %} {% endif %}
 *   {% for x in liste %} {% endfor %}
 *   {% extends "layout" %}     le template s'insère dans un parent
 *   {% block nom %} ... {% endblock %}   définit (enfant) ou affiche avec valeur par défaut (parent)
 *   {% yield nom %}            emplacement d'un bloc dans le parent
 *
 * Dans les expressions : variable, variable.cle (tableau ou objet), not / and / or, littéraux PHP.
 */
final class Template
{
    private ?string $parent = null;
    /** @var array<string, string> */
    private array $blocs = [];
    /** @var string[] */
    private array $pileBlocs = [];

    public function __construct(
        private readonly string $dossierTemplates,
        private readonly string $dossierCache,
        private readonly string $extension = '.html',
    ) {
    }

    public function rendre(string $nom, array $donnees = []): string
    {
        $this->blocs = [];

        return $this->executer($nom, $donnees);
    }

    // ----- exécution -------------------------------------------------------------------------

    private function executer(string $nom, array $donnees): string
    {
        $compile = $this->compiler($nom);
        $this->parent = null;

        extract($donnees, EXTR_SKIP);
        ob_start();
        include $compile;
        $sortie = ob_get_clean();

        // Un enfant ne produit rien par lui-même : il a rempli des blocs, c'est le parent qui affiche.
        if ($this->parent !== null) {
            return $this->executer($this->parent, $donnees);
        }

        return $sortie;
    }

    // Appelées par le code compilé.
    protected function etendre(string $parent): void
    {
        $this->parent = $parent;
    }

    protected function debutBloc(string $nom): void
    {
        $this->pileBlocs[] = $nom;
        ob_start();
    }

    protected function finBloc(): void
    {
        $nom = array_pop($this->pileBlocs) ?? throw new RuntimeException('endblock sans block');
        $contenu = ob_get_clean();

        if ($this->parent !== null) {
            // Dans un enfant : on mémorise, sans afficher.
            $this->blocs[$nom] ??= $contenu;
        } else {
            // Dans un parent : le contenu du template est la valeur par défaut, l'enfant a priorité.
            echo $this->blocs[$nom] ?? $contenu;
        }
    }

    protected function bloc(string $nom): string
    {
        return $this->blocs[$nom] ?? '';
    }

    protected function e(mixed $valeur): string
    {
        return htmlspecialchars((string) ($valeur ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function acceder(mixed $base, string $cle): mixed
    {
        if (is_array($base)) {
            return $base[$cle] ?? null;
        }
        if (is_object($base)) {
            return $base->$cle ?? (method_exists($base, $cle) ? $base->$cle() : null);
        }

        return null;
    }

    // ----- compilation -----------------------------------------------------------------------

    private function compiler(string $nom): string
    {
        $source = $this->dossierTemplates . '/' . $nom . $this->extension;
        if (!is_file($source)) {
            throw new RuntimeException("Template introuvable : $source");
        }

        if (!is_dir($this->dossierCache) && !mkdir($this->dossierCache, 0777, true)) {
            throw new RuntimeException("Impossible de créer le dossier de cache : {$this->dossierCache}");
        }

        $compile = $this->dossierCache . '/' . md5($source) . '.php';

        if (!is_file($compile) || filemtime($compile) < filemtime($source)) {
            file_put_contents($compile, "<?php /* compilé depuis $source */ ?>" . $this->traduire(file_get_contents($source)));
        }

        return $compile;
    }

    // Les remplacements restent sur la même ligne : un numéro de ligne dans le fichier compilé
    // correspond au même numéro dans le template source.
    private function traduire(string $tpl): string
    {
        $tpl = preg_replace_callback('/\{%\s*(.*?)\s*%\}/s', fn($m) => $this->traduireBalise($m[1]), $tpl);
        $tpl = preg_replace_callback('/\{!\s*(.*?)\s*!\}/s', fn($m) => '<?= ' . $this->expression($m[1]) . ' ?>', $tpl);
        $tpl = preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/s', fn($m) => '<?= $this->e(' . $this->expression($m[1]) . ') ?>', $tpl);

        return $tpl;
    }

    private function traduireBalise(string $balise): string
    {
        [$mot, $reste] = array_pad(preg_split('/\s+/', $balise, 2), 2, '');

        return match ($mot) {
            'extends'  => '<?php $this->etendre(' . $reste . '); ?>',
            'block'    => '<?php $this->debutBloc(\'' . $reste . '\'); ?>',
            'endblock' => '<?php $this->finBloc(); ?>',
            'yield'    => '<?= $this->bloc(\'' . $reste . '\') ?>',
            'if'       => '<?php if (' . $this->expression($reste) . '): ?>',
            'elseif'   => '<?php elseif (' . $this->expression($reste) . '): ?>',
            'else'     => '<?php else: ?>',
            'endif'    => '<?php endif; ?>',
            'for'      => $this->traduireFor($reste),
            'endfor'   => '<?php endforeach; ?>',
            default    => throw new RuntimeException("Balise inconnue : {% $balise %}"),
        };
    }

    private function traduireFor(string $reste): string
    {
        if (!preg_match('/^(\w+)\s+in\s+(.+)$/s', $reste, $m)) {
            throw new RuntimeException("Syntaxe attendue : {% for element in liste %}, reçu : $reste");
        }

        return '<?php foreach (' . $this->expression($m[2]) . ' as $' . $m[1] . '): ?>';
    }

    // Transforme une expression de template en expression PHP.
    private function expression(string $expr): string
    {
        // 1. Mettre les chaînes littérales de côté pour ne pas les modifier.
        $chaines = [];
        $expr = preg_replace_callback('/"[^"]*"|\'[^\']*\'/', function ($m) use (&$chaines) {
            $chaines[] = $m[0];
            return "\0" . (count($chaines) - 1) . "\0";
        }, $expr);

        // 2. Opérateurs en toutes lettres.
        $expr = preg_replace(['/\bnot\s+/', '/\band\b/', '/\bor\b/'], ['!', '&&', '||'], $expr);

        // 3. Identifiants -> variables ; chemins pointés -> accès sécurisé.
        $expr = preg_replace_callback(
            '/(?<![\$\w>])([a-zA-Z_]\w*)((?:\.[a-zA-Z_]\w*)+|\b)/',
            function ($m) {
                if (in_array(strtolower($m[1]), ['true', 'false', 'null'], true)) {
                    return $m[1];
                }
                $php = '$' . $m[1];
                foreach (array_filter(explode('.', $m[2])) as $segment) {
                    $php = '$this->acceder(' . $php . ', \'' . $segment . '\')';
                }
                return $php;
            },
            $expr
        );

        // 4. Remettre les chaînes.
        return preg_replace_callback("/\0(\d+)\0/", fn($m) => $chaines[(int) $m[1]], $expr);
    }
}
