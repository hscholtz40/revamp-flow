<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { authInputClass, authLabelClass } from '@/lib/authStyles';
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

        <form class="space-y-6" @submit.prevent="submit">
            <div class="space-y-5">
                <div class="space-y-2">
                    <Label for="password" :class="authLabelClass">New password</Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        :class="authInputClass"
                        autofocus
                        placeholder="New password"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="space-y-2">
                    <Label for="password_confirmation" :class="authLabelClass">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        :class="authInputClass"
                        placeholder="Confirm password"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" variant="auth" size="auth" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="mr-2 h-5 w-5 animate-spin" />
                    {{ form.processing ? 'Saving...' : 'Save and continue' }}
                </Button>
            </div>
        </form>
    </AuthLayout>
</template>
