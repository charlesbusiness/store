<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The contract between the record views and the Alpine component behind them.
 *
 * These exist because of a specific failure. The Measurement dropdown rendered
 * empty on the Buy and Sell tabs for a long time with a completely green suite:
 * the view iterated a bare `families`, which recordForm() never declared, so
 * x-for iterated undefined and produced nothing. HTTP 200, payload correct,
 * dropdown empty.
 *
 * Nothing caught it, because every existing check looked at one end of the wire
 * or the other. Asserting the JSON proves the server sent a key; asserting the
 * view renders proves the blade compiled. Neither can see that a template asks
 * for a name the browser cannot resolve. So both ends are checked here.
 */
class AlpineBindingsTest extends TestCase
{
    /**
     * Every name a record view binds must exist on the component.
     *
     * Deliberately approximate: it reads whole attributes off the blade and
     * compares names, so it cannot follow `line.a.b` into a nested object, or
     * check the shape of something the browser builds at runtime. It is a net for
     * the top-level-name class of mistake, not a type checker. Attribute values it
     * does not recognise as bindings are ignored rather than guessed at, so an
     * Alpine global it has not heard of will not fail it spuriously.
     */
    public function test_every_binding_in_the_record_views_exists_on_the_component(): void
    {
        $component = $this->componentMembers();
        $missing = [];

        foreach ($this->recordPartials() as $partial) {
            $html = file_get_contents($partial);
            $view = basename($partial, '.blade.php');
            $loopVars = [];

            // x-for has two halves and only one of them is a free name: "x in
            // things" and "(x, i) in things" both declare x and i, while things is
            // a reference the component has to own. Reading only the left half is
            // how this very test nearly missed the bug it was written for.
            //
            // Document order matters, since a nested x-for can iterate over the
            // variable an outer one just declared, as line.packs does.
            preg_match_all(
                '/x-for="\(?\s*([a-zA-Z_]\w*)\s*(?:,\s*[a-zA-Z_]\w*)?\s*\)?\s+in\s+([a-zA-Z_][\w.]*)/',
                $html,
                $matches,
                PREG_SET_ORDER,
            );

            foreach ($matches as [, $variable, $iterable]) {
                $loopVars[$variable] = true;

                foreach (array_keys($this->unresolved($iterable, $component + $loopVars)) as $name) {
                    $missing[$view][] = $name.' (x-for over)';
                }
            }

            foreach ($this->bindingAttributes() as $attribute) {
                preg_match_all('/'.preg_quote($attribute, '/').'="([^"]*)"/', $html, $matches);

                foreach ($matches[1] as $expression) {
                    if (! preg_match('/^\s*\(?\s*([a-zA-Z_]\w*)/', $expression, $head)) {
                        continue;
                    }

                    if (isset($loopVars[$head[1]]) || isset($component[$head[1]])) {
                        continue;
                    }

                    $missing[$view][] = $head[1].' ('.$attribute.')';
                }
            }
        }

        $this->assertSame([], $missing, 'the record views bind names the component does not have');
    }

    /**
     * Every config.X the browser reads must actually be sent.
     *
     * The mirror of the check above, and it fails differently: that one catches a
     * view reaching for something absent, this one catches the payload quietly
     * losing a key, which is just as silent.
     */
    public function test_every_config_key_the_browser_reads_is_sent(): void
    {
        $javascript = '';

        foreach (['stockly.js', 'packs.js', 'record-form.js'] as $file) {
            $javascript .= file_get_contents(public_path('js/'.$file));
        }

        foreach ($this->recordPartials() as $partial) {
            $javascript .= file_get_contents($partial);
        }

        preg_match_all('/config\.([a-zA-Z_]\w*)/', $javascript, $matches);
        $read = array_values(array_unique($matches[1]));

        $this->seedUnits();
        $this->actingAs($this->user());
        $config = $this->recordFormConfig(
            $this->get(route('purchases.create'))->assertOk()->getContent(),
        );

        foreach ($read as $key) {
            $this->assertArrayHasKey(
                $key,
                $config,
                "the browser reads config.$key but the record form does not send it",
            );
        }
    }

    public function test_alpine_starts_after_the_record_modules_are_ready(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $alpine = file_get_contents(public_path('js/alpine.min.js'));

        $this->assertStringContainsString('window.deferLoadingAlpine = function(callback)', $layout);
        $this->assertStringContainsString('window.markModuleReady = function(name)', $layout);
        $this->assertStringNotContainsString('window.Alpine.start();', $layout);
        $this->assertStringContainsString(
            'window.deferLoadingAlpine?window.deferLoadingAlpine(()=>Vt.start())',
            $alpine,
        );
    }

    /** @return array<int, string> */
    private function recordPartials(): array
    {
        return glob(resource_path('views/records/partials/*.blade.php'));
    }

    /** Attributes whose value is an expression, as opposed to a plain name. */
    private function bindingAttributes(): array
    {
        return ['x-model', 'x-text', 'x-show', 'x-bind', '@click', '@change', '@input', ':disabled', 'x-init'];
    }

    /**
     * A reference the component must own, as [name => true] when it does not.
     *
     * Only the root of a dotted reference is judged, so config.families is held to
     * `config` and the rest is left to the payload check above.
     *
     * @param  array<string, true>  $known
     * @return array<string, true>
     */
    private function unresolved(string $reference, array $known): array
    {
        $root = explode('.', $reference)[0];

        return isset($known[$root]) ? [] : [$root => true];
    }

    /**
     * Every name the record form component owns: state keys, getters, setters and
     * methods, plus config (which is one of them) and the globals the browser and
     * Alpine provide without anyone declaring them.
     *
     * @return array<string, true>
     */
    private function componentMembers(): array
    {
        $members = [];

        // Every script recordForm() mixes in, not just the one holding its state.
        // The presentation mixin was split out for line count, and a binding
        // resolving to a getter that lives over there would fail at runtime while
        // this test still passed -- which is exactly the class of bug it exists
        // for. Add a new mixin here when one is introduced.
        foreach (['record-form.js', 'packs.js', 'record-display.js', 'record-form/searchable-select.js'] as $file) {
            $javascript = file_get_contents(public_path('js/'.$file));

            // State keys and accessors: "name:", "get name() {", "set name(v) {"
            preg_match_all('/^\s*(?:get |set )?([a-zA-Z_]\w*)\s*(?:\(|:)/m', $javascript, $matches);
            foreach ($matches[1] as $name) {
                $members[$name] = true;
            }

            // Mixin methods, which sit one level shallower.
            preg_match_all('/^\s{2}([a-zA-Z_]\w*)\s*\(/m', $javascript, $matches);
            foreach ($matches[1] as $name) {
                $members[$name] = true;
            }
        }

        foreach (['window', 'document', 'console', 'this', 'config', '$el', '$refs', '$store', '$event'] as $global) {
            $members[$global] = true;
        }

        return $members;
    }
}
