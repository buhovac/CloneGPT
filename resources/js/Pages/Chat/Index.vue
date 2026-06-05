<script setup lang="ts">
import { ref, nextTick, watch, onMounted, shallowRef } from 'vue';
import { useForm } from '@inertiajs/vue3';
import type MarkdownItType from 'markdown-it';

interface Model {
    id: string;
    name: string;
}

interface Message {
    id: number;
    role: 'user' | 'assistant' | 'system';
    content: string;
}

interface Conversation {
    id: number;
    title: string;
    model: string;
    updated_at: string;
    messages?: Message[];
}

const props = defineProps<{
    conversations: Conversation[];
    models: Model[];
    defaultModel: string;
    activeConversation?: Conversation;
}>();

// Markdown parser — lazy init, samo u browseru (SSR fix)
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

// Form za novu poruku
const messageForm = useForm({ content: '' });

// Form za promjenu modela
const modelForm = useForm({
    model: props.activeConversation?.model ?? props.defaultModel,
});

// Form za novu konverzaciju
const newConversationForm = useForm({ model: props.defaultModel });

// Scroll na kraj poruka
const messagesEnd = ref<HTMLElement | null>(null);

const scrollToBottom = () => {
    nextTick(() => messagesEnd.value?.scrollIntoView({ behavior: 'smooth' }));
};

watch(() => props.activeConversation?.messages?.length, scrollToBottom, {
    immediate: true,
});

// Nova konverzacija
const createConversation = () => {
    newConversationForm.post(route('chat.store'));
};

// Slanje poruke
const sendMessage = () => {
    if (!messageForm.content.trim() || !props.activeConversation) {
        return;
    }

    messageForm.post(
        route('chat.messages.store', props.activeConversation.id),
        {
            onSuccess: () => {
                messageForm.reset('content');
            },
        },
    );
};

// Promjena modela
const updateModel = () => {
    if (!props.activeConversation) {
        newConversationForm.model = modelForm.model;

        return;
    }

    modelForm.patch(route('chat.model', props.activeConversation.id));
};
</script>

<template>
    <div
        class="flex h-screen bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100"
    >
        <!-- SIDEBAR: lista konverzacija -->
        <aside
            class="flex w-64 shrink-0 flex-col border-r border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="border-b border-gray-200 p-4 dark:border-gray-800">
                <h1 class="mb-3 text-lg font-bold">💬 Mini ChatGPT</h1>

                <!-- Odabir modela -->
                <select
                    v-model="modelForm.model"
                    class="mb-3 w-full rounded border-gray-300 text-xs dark:border-gray-700 dark:bg-gray-800"
                    @change="updateModel"
                >
                    <option v-for="m in props.models" :key="m.id" :value="m.id">
                        {{ m.name }}
                    </option>
                </select>

                <!-- Nova konverzacija -->
                <button
                    :disabled="newConversationForm.processing"
                    class="w-full rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-50"
                    @click="createConversation"
                >
                    + Nouvelle conversation
                </button>
            </div>

            <!-- Lista konverzacija (DESC po updated_at) -->
            <nav class="flex-1 space-y-1 overflow-y-auto p-2">
                <a
                    v-for="conv in props.conversations"
                    :key="conv.id"
                    :href="route('chat.show', conv.id)"
                    class="block truncate rounded-md px-3 py-2 text-sm transition"
                    :class="
                        props.activeConversation?.id === conv.id
                            ? 'bg-indigo-100 font-semibold text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300'
                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'
                    "
                >
                    {{ conv.title }}
                </a>

                <p
                    v-if="!props.conversations.length"
                    class="mt-4 text-center text-xs text-gray-400"
                >
                    Aucune conversation
                </p>
            </nav>
        </aside>

        <!-- MAIN: chat area -->
        <main class="flex min-w-0 flex-1 flex-col">
            <!-- Header konverzacije -->
            <header
                class="border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-800 dark:bg-gray-900"
            >
                <h2 class="truncate font-semibold">
                    {{
                        props.activeConversation?.title ??
                        'Sélectionnez ou créez une conversation'
                    }}
                </h2>

                <p
                    v-if="props.activeConversation"
                    class="mt-0.5 text-xs text-gray-400"
                >
                    Modèle : {{ props.activeConversation.model }}
                </p>
            </header>

            <!-- Poruke -->
            <div class="flex-1 space-y-4 overflow-y-auto px-6 py-4">
                <!-- Placeholder kad nema konverzacije -->
                <div
                    v-if="!props.activeConversation"
                    class="flex h-full items-center justify-center text-gray-400"
                >
                    <p>Créez une nouvelle conversation pour commencer.</p>
                </div>

                <!-- Poruke konverzacije -->
                <template v-else>
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
                                    ? 'bg-indigo-600 text-white'
                                    : 'border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800'
                            "
                        >
                            <!-- User poruke kao plain text -->
                            <template v-if="msg.role === 'user'">
                                {{ msg.content }}
                            </template>

                            <!-- Assistant poruke sa Markdown -->
                            <div
                                v-else
                                class="prose prose-sm max-w-none dark:prose-invert"
                                v-html="renderMarkdown(msg.content)"
                            />
                        </div>
                    </div>

                    <!-- Loader dok API odgovara -->
                    <div
                        v-if="messageForm.processing"
                        class="flex justify-start"
                    >
                        <div
                            class="flex items-center gap-2 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800"
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
                            <span class="ml-1">En cours...</span>
                        </div>
                    </div>

                    <div ref="messagesEnd" />
                </template>
            </div>

            <!-- Input area -->
            <div
                v-if="props.activeConversation"
                class="border-t border-gray-200 bg-white px-6 py-4 dark:border-gray-800 dark:bg-gray-900"
            >
                <form
                    class="flex items-end gap-3"
                    @submit.prevent="sendMessage"
                >
                    <textarea
                        v-model="messageForm.content"
                        rows="2"
                        placeholder="Envoyez un message..."
                        :disabled="messageForm.processing"
                        class="flex-1 resize-none rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800"
                        @keydown.enter.exact.prevent="sendMessage"
                    />

                    <button
                        type="submit"
                        :disabled="
                            messageForm.processing ||
                            !messageForm.content.trim()
                        "
                        class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-40"
                    >
                        Envoyer
                    </button>
                </form>

                <p class="mt-1 text-xs text-gray-400">
                    Entrée pour envoyer, Maj+Entrée pour nouvelle ligne
                </p>
            </div>
        </main>
    </div>
</template>
