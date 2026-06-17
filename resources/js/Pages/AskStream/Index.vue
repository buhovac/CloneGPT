<script setup lang="ts">
import { computed, onMounted, shallowRef } from 'vue';
import { useStream } from '@laravel/stream-vue';
import { index as streamIndex, post as streamPost } from '@/routes/stream';
import type MarkdownItType from 'markdown-it';

interface Model {
    id: string;
    name: string;
}

const props = defineProps<{
    models: Model[];
    selectedModel: string;
    selectedModelDetails: Record<string, unknown> | null;
}>();

// Markdown — lazy init pour SSR
const md = shallowRef<MarkdownItType | null>(null);

onMounted(async () => {
    const [{ default: MarkdownIt }, { default: hljs }] = await Promise.all([
        import('markdown-it'),
        import('highlight.js'),
    ]);

    await import('highlight.js/styles/github-dark.css');

    md.value = new MarkdownIt({
        highlight(str: string, lang: string) {
            if (lang && hljs.getLanguage(lang)) {
                try {
                    return hljs.highlight(str, { language: lang }).value;
                } catch {}
            }

            return '';
        },
    });
});

const renderMarkdown = (content: string): string => {
    if (!md.value) return content;
    return md.value.render(content);
};

// Form state
const message = shallowRef('');
const model = shallowRef(props.selectedModel);
const temperature = shallowRef(1.0);
const reasoningEffort = shallowRef<'low' | 'medium' | 'high' | null>(null);

// useStream hook
const { data, isFetching, isStreaming, send, cancel } = useStream(
    streamPost().url,
    {
        onFinish: () => {
            message.value = '';
        },
        onError: (err: Error) => {
            console.error('Erreur streaming:', err);
        },
    },
);

// Parsing reasoning markers
const streamedContent = computed(() => {
    if (!data.value) return '';

    return data.value
        .replace(/\[REASONING\][\s\S]*?\[\/REASONING\]/g, '')
        .trim();
});

const streamedReasoning = computed(() => {
    if (!data.value) return '';

    const matches = data.value.match(/\[REASONING\]([\s\S]*?)\[\/REASONING\]/g);

    if (!matches) return '';

    return matches
        .map((m) =>
            m.replace(/\[REASONING\]/g, '').replace(/\[\/REASONING\]/g, ''),
        )
        .join('');
});

const isLoading = computed(() => isFetching.value || isStreaming.value);

// Provjeri podržava li model reasoning
const supportsReasoning = computed(() => {
    if (!props.selectedModelDetails) return false;
    const params = props.selectedModelDetails.supported_parameters as
        | string[]
        | undefined;

    return params?.includes('reasoning') ?? false;
});

function submit() {
    if (!message.value.trim() || isLoading.value) return;

    send({
        message: message.value,
        model: model.value,
        temperature: temperature.value,
        reasoning_effort: reasoningEffort.value,
    });
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        submit();
    }
}
</script>

<template>
    <div class="mx-auto max-w-3xl px-4 py-10">
        <h1 class="mb-1 text-2xl font-medium">⚡ Streaming SSE</h1>
        <p class="mb-8 text-sm text-gray-500 dark:text-gray-400">
            Les réponses s'affichent en temps réel, mot par mot.
        </p>

        <!-- Formulaire -->
        <div
            class="mb-6 space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900"
        >
            <!-- Modèle -->
            <div>
                <label
                    class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400"
                >
                    Modèle
                </label>
                <select
                    v-model="model"
                    class="w-full rounded-xl border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <option v-for="m in props.models" :key="m.id" :value="m.id">
                        {{ m.name }}
                    </option>
                </select>
            </div>

            <!-- Température -->
            <div>
                <label
                    class="mb-1 flex items-center justify-between text-xs font-medium text-gray-500 dark:text-gray-400"
                >
                    <span>Température</span>
                    <span class="font-mono text-surface">{{
                        temperature.toFixed(1)
                    }}</span>
                </label>
                <input
                    v-model.number="temperature"
                    type="range"
                    min="0"
                    max="2"
                    step="0.1"
                    class="w-full accent-brand"
                />
                <div class="mt-1 flex justify-between text-xs text-gray-400">
                    <span>0 — Précis</span>
                    <span>2 — Créatif</span>
                </div>
            </div>

            <!-- Reasoning effort (si supporté) -->
            <div v-if="supportsReasoning">
                <label
                    class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400"
                >
                    Effort de raisonnement
                </label>
                <div class="flex gap-2">
                    <button
                        v-for="effort in ['low', 'medium', 'high']"
                        :key="effort"
                        type="button"
                        class="rounded-lg border px-3 py-1.5 text-xs font-medium transition"
                        :class="
                            reasoningEffort === effort
                                ? 'border-brand bg-brand text-surface'
                                : 'border-gray-300 text-gray-600 hover:border-brand/70 dark:border-gray-700 dark:text-gray-300'
                        "
                        @click="
                            reasoningEffort =
                                reasoningEffort === effort
                                    ? null
                                    : (effort as 'low' | 'medium' | 'high')
                        "
                    >
                        {{ effort }}
                    </button>
                </div>
            </div>

            <!-- Message -->
            <div>
                <label
                    class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400"
                >
                    Message
                </label>
                <textarea
                    v-model="message"
                    rows="4"
                    placeholder="Posez votre question..."
                    :disabled="isLoading"
                    class="w-full resize-none rounded-xl border-gray-300 text-sm shadow-sm focus:border-brand focus:ring-brand disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800"
                    @keydown="handleKeydown"
                />
            </div>

            <!-- Boutons -->
            <div class="flex items-center justify-end gap-3">
                <button
                    v-if="isStreaming"
                    type="button"
                    class="rounded-xl border border-red-300 px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:border-red-700 dark:text-red-400"
                    @click="cancel()"
                >
                    Annuler
                </button>
                <button
                    type="button"
                    :disabled="!message.trim() || isLoading"
                    class="rounded-xl bg-brand px-6 py-2 text-sm font-medium text-surface transition hover:bg-brand/80 disabled:opacity-40"
                    @click="submit"
                >
                    {{
                        isFetching
                            ? 'Connexion...'
                            : isStreaming
                              ? 'Génération...'
                              : 'Envoyer'
                    }}
                </button>
            </div>
        </div>

        <!-- Réponse -->
        <div v-if="data" class="space-y-4">
            <!-- Reasoning trace -->
            <div
                v-if="streamedReasoning"
                class="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950"
            >
                <h4
                    class="mb-2 text-xs font-semibold tracking-wide text-amber-700 uppercase dark:text-amber-400"
                >
                    🧠 Trace de raisonnement
                </h4>
                <pre
                    class="text-xs whitespace-pre-wrap text-amber-800 dark:text-amber-300"
                    >{{ streamedReasoning }}</pre
                >
            </div>

            <!-- Contenu principal -->
            <div
                class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900"
            >
                <!-- Curseur clignotant pendant le stream -->
                <div
                    v-if="streamedContent || isStreaming"
                    class="prose prose-sm max-w-none dark:prose-invert"
                >
                    <div v-html="renderMarkdown(streamedContent)" />
                    <span
                        v-if="isStreaming"
                        class="inline-block h-4 w-0.5 animate-pulse bg-brand"
                    />
                </div>
            </div>
        </div>

        <!-- Placeholder -->
        <div
            v-else
            class="flex h-32 items-center justify-center rounded-2xl border border-dashed border-gray-300 text-sm text-gray-400 dark:border-gray-700"
        >
            La réponse apparaîtra ici en temps réel...
        </div>
    </div>
</template>
