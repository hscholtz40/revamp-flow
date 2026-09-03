<script setup lang="ts">
import PasswordResetLinkController from '@/actions/App/Http/Controllers/Auth/PasswordResetLinkController';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { authInputClass, authLabelClass, authLinkClass } from '@/lib/authStyles';
import { login } from '@/routes';
import { Form, Head } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout
        title="Forgot password"
        description="Enter your email to receive a password reset link"
    >
        <Head title="Forgot password" />

        <div
            v-if="status"
            class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-center text-sm font-medium text-green-700"
        >
            {{ status }}
        </div>

        <Form
            v-bind="PasswordResetLinkController.store.form()"
            v-slot="{ errors, processing }"
            class="space-y-6"
        >
            <div class="space-y-5">
                <div class="space-y-2">
                    <Label for="email" :class="authLabelClass">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        autofocus
                        placeholder="Enter your email"
                        :class="authInputClass"
                    />
                    <InputError :message="errors.email" />
                </div>

                <Button
                    type="submit"
                    variant="auth"
                    size="auth"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <LoaderCircle
                        v-if="processing"
                        class="mr-2 h-5 w-5 animate-spin"
                    />
                    {{ processing ? 'Sending reset link...' : 'Email password reset link' }}
                </Button>
            </div>

            <div class="text-center">
                <p class="text-sm text-gray-600">
                    Or, return to
                    <TextLink
                        :href="login()"
                        :class="['ml-1', authLinkClass]"
                    >
                        log in
                    </TextLink>
                </p>
            </div>
        </Form>
    </AuthLayout>
</template>
