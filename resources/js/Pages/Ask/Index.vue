<script setup>
import { useForm } from '@inertiajs/vue3';
import hljs from 'highlight.js';
import MarkdownIt from 'markdown-it';
import 'highlight.js/styles/github-dark.css';

// 1. Prihvaćanje props-a iz AskController-a
const props = defineProps({
    models: Array,
    selectedModel: String,
    message: String,
    response: String,
    error: String,
});

// 2. Inicijalizacija useForm reaktivnog objekta
const form = useForm({
    message: props.message ?? '', // Ako je korisnik već poslao poruku, zadrži je u polju
    model: props.selectedModel ?? '', // Postavlja zadani model iz kontrolera
});

// 3. Konfiguracija markdown-it parsera s highlight.js
const md = new MarkdownIt({
    highlight: function (str, lang) {
        if (lang && hljs.getLanguage(lang)) {
            try {
                return hljs.highlight(str, { language: lang }).value;
            } catch (__) {}
        }

        return '';
    },
});

// 4. Slanje forme na rutu '/ask' izravno kao string
const submit = () => {
    form.post('/ask');
};
</script>

<template>
    <div
        class="min-h-screen bg-gray-50 py-12 text-gray-900 dark:bg-gray-950 dark:text-gray-100"
    >
        <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
            <div class="mb-8 text-center">
                <h1 class="text-3xl font-extrabold tracking-tight">
                    SYNTH Terminal
                </h1>
                <p class="mt-2 text-gray-500">
                    Interface de requête directe aux modèles IA
                </p>
            </div>

            <div
                v-if="props.error"
                class="rounded-md border border-red-200 bg-red-50 p-4 text-red-600 dark:border-red-900 dark:bg-red-950/50 dark:text-red-400"
            >
                <span class="font-bold">Erreur:</span> {{ props.error }}
            </div>

            <div
                class="overflow-hidden border border-gray-200 bg-white p-6 shadow-sm sm:rounded-lg dark:border-gray-800 dark:bg-gray-900"
            >
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label
                            for="model"
                            class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Choisir un modèle
                        </label>
                        <select
                            id="model"
                            v-model="form.model"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800"
                        >
                            <option
                                v-for="model in props.models"
                                :key="model.id"
                                :value="model.id"
                            >
                                {{ model.name }} ({{ model.id }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            for="message"
                            class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Que puis-je faire pour vous ?
                        </label>
                        <textarea
                            id="message"
                            v-model="form.message"
                            rows="4"
                            placeholder="Envoyez un message pour démarrer une conversation..."
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800"
                            :disabled="form.processing"
                        ></textarea>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition duration-150 ease-in-out hover:bg-indigo-700 focus:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none active:bg-indigo-900 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Chargement...' : 'Envoyer' }}
                        </button>
                    </div>
                </form>
            </div>

            <div
                v-if="props.response"
                class="border border-gray-200 bg-white p-6 shadow-sm sm:rounded-lg dark:border-gray-800 dark:bg-gray-900"
            >
                <h3
                    class="mb-4 border-b border-gray-200 pb-2 text-lg font-bold text-gray-500 dark:border-gray-800"
                >
                    Affichage de la réponse
                </h3>

                <div
                    class="prose max-w-none prose-indigo dark:prose-invert"
                    v-html="md.render(props.response)"
                />
            </div>
        </div>
    </div>
</template>
