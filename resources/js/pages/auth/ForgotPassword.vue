<script setup lang="ts">
import PasswordResetLinkController from '@/actions/App/Http/Controllers/Auth/PasswordResetLinkController';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
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
                    <Label for="email" class="text-sm font-medium text-gray-700">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        autofocus
                        placeholder="Enter your email"
                        class="h-12 border-gray-300 px-4 text-base focus:border-[#f7941d] focus:ring-[#f7941d]"
                    />
                    <InputError :message="errors.email" />
                </div>

                <Button
                    type="submit"
                    class="h-12 w-full rounded-lg bg-[#f7941d] text-base font-medium text-white transition-colors duration-200 hover:bg-[#d97800] focus:ring-2 focus:ring-[#f7941d] focus:ring-offset-2"
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
                        class="ml-1 font-medium text-[#f7941d] hover:text-[#d97800]"
                    >
                        log in
                    </TextLink>
                </p>
            </div>
        </Form>
    </AuthLayout>
</template>
