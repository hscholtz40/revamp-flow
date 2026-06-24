<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const form = useForm({
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put('/password/required');
};
</script>

<template>
    <AuthLayout
        title="Set a new password"
        description="Your account was created with a temporary password. Choose a new password to continue."
    >
        <Head title="Set a new password" />

        <form @submit.prevent="submit" class="grid gap-6">
            <div class="grid gap-2">
                <Label for="password">New password</Label>
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    class="mt-1 block w-full"
                    autofocus
                    placeholder="New password"
                />
                <InputError :message="form.errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <Input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    class="mt-1 block w-full"
                    placeholder="Confirm password"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <Button type="submit" class="mt-4 w-full" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                <span v-else>Save and continue</span>
            </Button>
        </form>
    </AuthLayout>
</template>
