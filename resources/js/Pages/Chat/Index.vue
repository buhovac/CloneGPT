<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';
import { useStream } from '@laravel/stream-vue';
import type MarkdownItType from 'markdown-it';
import { ref, nextTick, watch, onMounted, shallowRef, computed } from 'vue';
import { show, store, model as modelRoute } from '@/routes/chat';
import { stream as messagesStream } from '@/routes/chat/messages';
import { store as tagsStore } from '@/routes/tags';
import { attach as tagsAttach, detach as tagsDetach } from '@/routes/tags';

interface Model {
    id: string;
    name: string;
}

interface Message {
    id: number;
    role: 'user' | 'assistant' | 'system';
    content: string;
}

interface Tag {
    id: number;
    name: string;
    color: string;
}

interface Conversation {
    id: number;
    title: string;
    model: string;
    updated_at: string;
    messages?: Message[];
    tags?: Tag[];
}

const props = defineProps<{
    conversations: Conversation[];
    models: Model[];
    defaultModel: string;
    activeConversation?: Conversation;
    allTags: Tag[];
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
    if (!md.value) {
        return content;
    }

    return md.value.render(content);
};

// Forms
const messageContent = ref('');
const temperature = ref(1.0);
const modelForm = useForm({
    model: props.activeConversation?.model ?? props.defaultModel,
});
const newConversationForm = useForm({ model: props.defaultModel });

// URL pour useStream — chaîne vide par défaut en l'absence de conversation
const streamUrl = computed(
    () => messagesStream(props.activeConversation?.id ?? 0).url,
);

// useStream hook
const { data, isFetching, isStreaming, send } = useStream(streamUrl, {
    onFinish: () => {
        messageContent.value = '';
        router.reload({ only: ['activeConversation', 'conversations'] });
    },
    onError: (err: Error) => {
        console.error('Streaming error:', err);
    },
});

// État de flux — suppression des marqueurs de raisonnement
const streamingContent = computed(() => {
    if (!data.value) {
        return '';
    }

    return data.value.replace(/\[REASONING][\s\S]*?\[\/REASONING]/g, '').trim();
});

const isLoading = computed(() => isFetching.value || isStreaming.value);

// Scroll
const messagesEnd = ref<HTMLElement | null>(null);

const scrollToBottom = () => {
    nextTick(() => messagesEnd.value?.scrollIntoView({ behavior: 'smooth' }));
};

watch(() => props.activeConversation?.messages?.length, scrollToBottom, {
    immediate: true,
});

watch(streamingContent, scrollToBottom);

// Nouvelle conversation
function createConversation() {
    router.post(
        store().url,
        { model: newConversationForm.model },
        {
            onSuccess: (page) => {
                // Inertia ne remonte pas le composant, donc useStream
                // garderait l'ancienne URL (/chat/0/stream). window.location
                // force la réinitialisation avec le bon ID de conversation.
                window.location.href = page.url;
            },
        },
    );
}

// Envoi d'un message via flux
function sendMessage() {
    if (
        !messageContent.value.trim() ||
        !props.activeConversation ||
        isLoading.value
    ) {
        return;
    }

    send({ content: messageContent.value, temperature: temperature.value });
}

// Modèle de changement
function updateModel() {
    if (!props.activeConversation) {
        newConversationForm.model = modelForm.model;

        return;
    }

    modelForm.patch(modelRoute(props.activeConversation.id).url);
}

// ─── Tags ───
const newTagName = ref('');
const tagForm = useForm({ name: '', color: '#beef35' });

function createTag() {
    if (!newTagName.value.trim()) {
        return;
    }

    tagForm.name = newTagName.value;
    tagForm.post(tagsStore().url, {
        preserveScroll: true,
        onSuccess: () => {
            newTagName.value = '';
            tagForm.reset();
        },
    });
}

function attachTag(tagId: number) {
    if (!props.activeConversation) {
        return;
    }

    router.post(
        tagsAttach(props.activeConversation.id).url,
        { tag_id: tagId },
        { preserveScroll: true, only: ['activeConversation', 'conversations'] },
    );
}

function detachTag(tagId: number) {
    if (!props.activeConversation) {
        return;
    }

    router.delete(
        tagsDetach({ conversation: props.activeConversation.id, tag: tagId })
            .url,
        {
            preserveScroll: true,
            only: ['activeConversation', 'conversations'],
        },
    );
}

// Étiquettes qui ne font PAS ENCORE partie d'une conversation active (pour le menu déroulant)
const availableTags = computed(() => {
    if (!props.activeConversation) {
        return props.allTags;
    }

    const activeIds = (props.activeConversation.tags ?? []).map((t) => t.id);

    return props.allTags.filter((t) => !activeIds.includes(t.id));
});
function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}
</script>

<template>
    <div
        class="flex h-screen bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100"
    >
        <!-- SIDEBAR -->
        <aside
            class="flex w-64 shrink-0 flex-col border-r border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="border-b border-gray-200 p-4 dark:border-gray-800">
                <h1 class="mb-3 text-lg font-bold">Yoat-Companion</h1>

                <select
                    v-model="modelForm.model"
                    class="mb-3 w-full rounded border-gray-300 text-xs dark:border-gray-700 dark:bg-gray-800"
                    @change="updateModel"
                >
                    <option v-for="m in props.models" :key="m.id" :value="m.id">
                        {{ m.name }}
                    </option>
                </select>
                <!-- Température -->
                <div class="mb-3">
                    <label
                        class="mb-1 flex items-center justify-between text-xs text-gray-400"
                    >
                        <span>Température</span>
                        <span class="font-mono text-brand">{{
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
                </div>

                <button
                    :disabled="newConversationForm.processing"
                    class="w-full rounded bg-brand px-3 py-2 text-sm font-medium text-surface transition hover:bg-brand/80 disabled:opacity-50"
                    @click="createConversation"
                >
                    + Nouvelle conversation
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto p-2">
                <a
                    v-for="conv in props.conversations"
                    :key="conv.id"
                    :href="show(conv.id).url"
                    class="block rounded-md px-3 py-2 text-sm transition"
                    :class="
                        props.activeConversation?.id === conv.id
                            ? 'bg-brand/15 font-semibold text-surface dark:bg-brand/20 dark:text-brand'
                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'
                    "
                >
                    <span class="block truncate">{{ conv.title }}</span>
                    <span
                        v-if="conv.tags?.length"
                        class="mt-1 flex flex-wrap gap-1"
                    >
                        <span
                            v-for="tag in conv.tags"
                            :key="tag.id"
                            class="rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                            :style="{
                                backgroundColor: tag.color + '33',
                                color: tag.color,
                            }"
                        >
                            {{ tag.name }}
                        </span>
                    </span>
                </a>

                <p
                    v-if="!props.conversations.length"
                    class="mt-4 text-center text-xs text-gray-400"
                >
                    Aucune conversation
                </p>
            </nav>
        </aside>

        <!-- MAIN -->
        <main class="flex min-w-0 flex-1 flex-col">
            <!-- Header -->
            <header
                class="border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-800 dark:bg-gray-900"
            >
                <h2 class="truncate font-semibold">
                    {{
                        props.activeConversation?.title ??
                        'Choisis ta voie, padawan'
                    }}
                </h2>
                <p
                    v-if="props.activeConversation"
                    class="mt-0.5 text-xs text-gray-400"
                >
                    Modèle : {{ props.activeConversation.model }}
                </p>
                <!-- Tag manager -->
                <div
                    v-if="props.activeConversation"
                    class="mt-2 flex flex-wrap items-center gap-2"
                >
                    <!-- Étiquettes de conversation -->
                    <span
                        v-for="tag in props.activeConversation.tags"
                        :key="tag.id"
                        class="group flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                        :style="{
                            backgroundColor: tag.color + '33',
                            color: tag.color,
                        }"
                    >
                        {{ tag.name }}
                        <button
                            class="opacity-50 transition hover:opacity-100"
                            title="Retirer"
                            @click="detachTag(tag.id)"
                        >
                            ✕
                        </button>
                    </span>

                    <!-- Menu déroulant pour ajouter une étiquette existante -->
                    <select
                        v-if="availableTags.length"
                        class="rounded-full border-gray-300 bg-transparent py-0.5 pr-7 pl-2 text-xs dark:border-gray-700"
                        @change="
                            attachTag(
                                Number(
                                    ($event.target as HTMLSelectElement).value,
                                ),
                            );
                            ($event.target as HTMLSelectElement).value = '';
                        "
                    >
                        <option value="" disabled selected>+ Tag</option>
                        <option
                            v-for="tag in availableTags"
                            :key="tag.id"
                            :value="tag.id"
                        >
                            {{ tag.name }}
                        </option>
                    </select>

                    <!-- Création d'une nouvelle balise -->
                    <form
                        class="flex items-center gap-1"
                        @submit.prevent="createTag"
                    >
                        <input
                            v-model="newTagName"
                            type="text"
                            placeholder="Nouveau tag"
                            class="w-24 rounded-full border-gray-300 bg-transparent px-2 py-0.5 text-xs dark:border-gray-700"
                        />
                    </form>
                </div>
            </header>

            <!-- Messages -->
            <div class="flex-1 space-y-4 overflow-y-auto px-6 py-4">
                <div
                    v-if="!props.activeConversation"
                    class="flex h-full items-center justify-center text-gray-400"
                >
                    <p>
                        Commencer ton entraînement, tu dois. Crée une
                        conversation.
                    </p>
                </div>

                <template v-else>
                    <!-- Messages existants de la base de données -->
                    <div
                        v-for="msg in props.activeConversation.messages"
                        :key="msg.id"
                        class="flex"
                        :class="
                            msg.role === 'user'
                                ? 'justify-end'
                                : 'justify-start'
                        "
                    >
                        <div
                            class="max-w-[75%] rounded-2xl px-4 py-3 text-sm shadow-sm"
                            :class="
                                msg.role === 'user'
                                    ? 'bg-brand text-surface'
                                    : 'border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800'
                            "
                        >
                            <template v-if="msg.role === 'user'">
                                {{ msg.content }}
                            </template>

                            <div
                                v-else
                                class="prose prose-sm max-w-none dark:prose-invert"
                                v-html="renderMarkdown(msg.content)"
                            />
                        </div>
                    </div>

                    <!-- Interface utilisateur optimiste — message à l'utilisateur pendant l'attente du flux -->
                    <div
                        v-if="isLoading && messageContent"
                        class="flex justify-end"
                    >
                        <div
                            class="max-w-[75%] rounded-2xl bg-brand px-4 py-3 text-sm text-surface opacity-70 shadow-sm"
                        >
                            {{ messageContent }}
                        </div>
                    </div>

                    <!-- Réponse en continu -->
                    <div
                        v-if="isFetching || isStreaming"
                        class="flex justify-start"
                    >
                        <div
                            class="max-w-[75%] rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800"
                        >
                            <!-- Chargement en cours pendant l'attente du premier morceau -->
                            <div
                                v-if="isFetching && !streamingContent"
                                class="flex items-center gap-2 text-gray-500"
                            >
                                <span class="animate-pulse">●</span>
                                <span
                                    class="animate-pulse"
                                    style="animation-delay: 0.2s"
                                    >●</span
                                >
                                <span
                                    class="animate-pulse"
                                    style="animation-delay: 0.4s"
                                    >●</span
                                >
                            </div>

                            <!-- Texte en continu -->
                            <div
                                v-if="streamingContent"
                                class="prose prose-sm max-w-none dark:prose-invert"
                            >
                                <div
                                    v-html="renderMarkdown(streamingContent)"
                                />
                                <span
                                    v-if="isStreaming"
                                    class="inline-block h-4 w-0.5 animate-pulse bg-brand"
                                />
                            </div>
                        </div>
                    </div>

                    <div ref="messagesEnd" />
                </template>
            </div>

            <!-- Input -->
            <div
                v-if="props.activeConversation"
                class="border-t border-gray-200 bg-white px-6 py-4 dark:border-gray-800 dark:bg-gray-900"
            >
                <form
                    class="flex items-end gap-3"
                    @submit.prevent="sendMessage"
                >
                    <textarea
                        v-model="messageContent"
                        rows="2"
                        placeholder="Ta question, pose-la, padawan..."
                        :disabled="isLoading"
                        class="flex-1 resize-none rounded-xl border-gray-300 text-sm shadow-sm focus:border-brand/70 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800"
                        @keydown="handleKeydown"
                    />
                    <button
                        type="submit"
                        :disabled="isLoading || !messageContent.trim()"
                        class="rounded-xl bg-brand px-5 py-2.5 text-sm font-medium text-surface transition hover:bg-brand/80 disabled:opacity-40"
                    >
                        {{
                            isFetching
                                ? 'Connexion...'
                                : isStreaming
                                  ? 'Génération...'
                                  : 'Envoyer'
                        }}
                    </button>
                </form>
                <p class="mt-1 text-xs text-gray-400">
                    Entrée pour envoyer, Maj+Entrée pour nouvelle ligne
                </p>
            </div>
        </main>
    </div>
</template>
