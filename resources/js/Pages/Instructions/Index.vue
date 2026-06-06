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
