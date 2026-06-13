<x-filament-panels::page>
    @if ($error)
        <x-filament::section>
            <p class="text-sm text-danger-600">{{ $error }}</p>
            <x-filament::button tag="a" :href="$this->backUrl()" color="gray" class="mt-3">Back to files</x-filament::button>
        </x-filament::section>
    @else
        <div class="text-xs text-gray-500 font-mono mb-2">
            {{ $this->currentSite()?->domain }}:{{ $path }}
        </div>

        {{-- CodeMirror enhances the textarea; if it fails to load, the plain
             textarea still works and saves. wire:ignore keeps Livewire from
             clobbering CodeMirror's DOM. --}}
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/lib/codemirror.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/theme/material-darker.min.css">

        <div wire:ignore
             x-data="{
                 cm: null,
                 init() {
                     const ta = this.$refs.ta;
                     const start = () => {
                         if (!window.CodeMirror) { this.fallback(ta); return; }
                         this.cm = window.CodeMirror.fromTextArea(ta, {
                             lineNumbers: true,
                             mode: @js($this->editorMode()),
                             theme: 'material-darker',
                             indentUnit: 4,
                             tabSize: 4,
                             lineWrapping: false,
                             autofocus: true,
                         });
                         this.cm.setSize('100%', '70vh');
                         this.cm.on('change', () => $wire.set('content', this.cm.getValue(), false));
                     };
                     if (window.CodeMirror) { start(); }
                     else {
                         const s = document.createElement('script');
                         s.src = 'https://cdn.jsdelivr.net/npm/codemirror@5.65.16/lib/codemirror.min.js';
                         s.onload = () => {
                             // load common modes, then init
                             const modes = ['xml','javascript','css','htmlmixed','php','clike','sql','yaml','markdown','shell'];
                             let pending = modes.length;
                             modes.forEach(m => {
                                 const ms = document.createElement('script');
                                 ms.src = `https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/${m}/${m}.min.js`;
                                 ms.onload = ms.onerror = () => { if (--pending === 0) start(); };
                                 document.head.appendChild(ms);
                             });
                         };
                         s.onerror = () => this.fallback(ta);
                         document.head.appendChild(s);
                     }
                 },
                 fallback(ta) {
                     ta.style.display = 'block';
                     ta.addEventListener('input', () => $wire.set('content', ta.value, false));
                     ta.addEventListener('keydown', (e) => {
                         if (e.key === 'Tab') {
                             e.preventDefault();
                             const s = ta.selectionStart, en = ta.selectionEnd;
                             ta.value = ta.value.slice(0, s) + '    ' + ta.value.slice(en);
                             ta.selectionStart = ta.selectionEnd = s + 4;
                             $wire.set('content', ta.value, false);
                         }
                     });
                 },
             }"
             x-init="init()"
             class="rounded-xl overflow-hidden border border-gray-200 dark:border-white/10">
            <textarea x-ref="ta"
                      class="w-full font-mono text-sm p-4 bg-gray-950 text-gray-100 border-0 focus:ring-0"
                      style="height:70vh"
                      spellcheck="false">{{ $content }}</textarea>
        </div>

        <div class="mt-3">
            <x-filament::button wire:click="save" icon="heroicon-m-check">Save</x-filament::button>
            <span class="text-xs text-gray-400 ml-2">or press Ctrl/Cmd + S</span>
        </div>
    @endif
</x-filament-panels::page>
