<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { update } from '@/routes/instructions';

const props = defineProps<{
    instructions: {
        custom_about: string | null;
        custom_behavior: string | null;
        custom_commands: string | null;
    };
}>();

const form = useForm({
    custom_about: props.instructions.custom_about ?? '',
    custom_behavior: props.instructions.custom_behavior ?? '',
    custom_commands: props.instructions.custom_commands ?? '',
});

function save() {
    form.patch(update().url);
}
</script>

<template>
    <div class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="mb-1 text-2xl font-medium">Instructions personnalisées</h1>
        <p class="mb-8 text-sm text-gray-500 dark:text-gray-400">
            Ces instructions sont ajoutées au prompt système de chaque
            conversation.
        </p>

        <!-- Success message -->
        <div
            v-if="form.recentlySuccessful"
            class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-950 dark:text-green-300"
        >
            Instructions sauvegardées.
        </div>

        <form class="space-y-6" @submit.prevent="save">
            <!-- À propos de vous -->
            <div>
                <label class="mb-1 block text-sm font-medium">
                    À propos de vous
                </label>
                <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                    Qui êtes-vous ? Profession, niveau d'expertise, centres
                    d'intérêt.
                </p>
                <textarea
                    v-model="form.custom_about"
                    rows="4"
                    placeholder="Je suis développeur web PHP/Laravel. J'ai 3 ans d'expérience..."
                    class="w-full resize-none px-5 py-5 rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800"
                />
                <p class="mt-1 text-right text-xs text-gray-400">
                    {{ form.custom_about.length }} / 2000
                </p>
            </div>

            <!-- Comportement -->
            <div>
                <label class="mb-1 block text-sm font-medium">
                    Comportement de l'assistant
                </label>
                <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                    Ton, format, longueur des réponses.
                </p>
                <textarea
                    v-model="form.custom_behavior"
                    rows="4"
                    placeholder="Réponds de manière concise. Utilise des exemples de code quand c'est pertinent..."
                    class="w-full resize-none px-5 py-5 rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800"
                />
                <p class="mt-1 text-right text-xs text-gray-400">
                    {{ form.custom_behavior.length }} / 2000
                </p>
            </div>

            <!-- Commandes -->
            <div>
                <label class="mb-1 block text-sm font-medium">
                    Commandes personnalisées
                </label>
                <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                    Raccourcis que vous pouvez utiliser dans le chat, ex:
                    /debug, /eli5.
                </p>
                <textarea
                    v-model="form.custom_commands"
                    rows="4"
                    placeholder="/debug → Analyse ce code et trouve les bugs potentiels.&#10;/eli5 → Explique ce concept simplement, avec une analogie."
                    class="w-full resize-none px-5 py-5 rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800"
                />
                <p class="mt-1 text-right text-xs text-gray-400">
                    {{ form.custom_commands.length }} / 2000
                </p>
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-end gap-4">
                <span
                    v-if="form.isDirty && !form.recentlySuccessful"
                    class="text-xs text-gray-400"
                >
                    Modifications non sauvegardées
                </span>
                <button
                    type="submit"
                    :disabled="form.processing || !form.isDirty"
                    class="rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-40"
                >
                    Sauvegarder
                </button>
            </div>
        </form>
    </div>
</template>
