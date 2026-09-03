<script setup lang="ts">
import AuthenticatedSessionController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { authCheckboxClass, authInputClass, authLabelClass, authLinkClass } from '@/lib/authStyles';
import { request } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <AuthBase
        title="Client Zone Login"
        description="Sign in to securely access your customer documents"
    >
        <Head title="Client Login" />

        <div
            v-if="status"
            class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-center text-sm font-medium text-green-700"
        >
            {{ status }}
        </div>

        <Form
            v-bind="AuthenticatedSessionController.storeClient.form()"
            :reset-on-success="['password']"
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
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="Enter your email"
                        :class="authInputClass"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label for="password" :class="authLabelClass">Password</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            :class="['text-sm', authLinkClass]"
                            :tabindex="5"
                        >
                            Forgot password?
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Enter your password"
                        :class="authInputClass"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex cursor-pointer items-center space-x-3">
                        <Checkbox
                            id="remember"
                            name="remember"
                            :tabindex="3"
                            :class="authCheckboxClass"
                        />
                        <span class="text-sm text-gray-700">Remember me for 30 days</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    variant="auth"
                    size="auth"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="client-login-button"
                >
                    <LoaderCircle
                        v-if="processing"
                        class="mr-2 h-5 w-5 animate-spin"
                    />
                    {{ processing ? 'Signing in...' : 'Sign in to Client Zone' }}
                </Button>
            </div>

            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Staff user?
                    <a href="/login" :class="['ml-1', authLinkClass]">
                        Staff Login
                    </a>
                </p>
                <p class="mt-2 text-sm text-gray-600">
                    Need client access?
                    <a href="/client-zone/register" :class="['ml-1', authLinkClass]">
                        Register
                    </a>
                </p>
            </div>
        </Form>
    </AuthBase>
</template>
