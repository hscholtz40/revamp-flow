<script setup lang="ts">
import AuthenticatedSessionController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
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
                    <Label for="email" class="text-sm font-medium text-gray-700">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="Enter your email"
                        class="h-12 px-4 text-base border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label for="password" class="text-sm font-medium text-gray-700">Password</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-sm text-blue-600 hover:text-blue-500 font-medium"
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
                        class="h-12 px-4 text-base border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3 cursor-pointer">
                        <Checkbox
                            id="remember"
                            name="remember"
                            :tabindex="3"
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        />
                        <span class="text-sm text-gray-700">Remember me for 30 days</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="w-full h-12 text-base font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors duration-200 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="client-login-button"
                >
                    <LoaderCircle
                        v-if="processing"
                        class="h-5 w-5 animate-spin mr-2"
                    />
                    {{ processing ? 'Signing in...' : 'Sign in to Client Zone' }}
                </Button>
            </div>

            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Staff user?
                    <a href="/login" class="ml-1 font-medium text-blue-600 hover:text-blue-500">
                        Staff Login
                    </a>
                </p>
                <p class="mt-2 text-sm text-gray-600">
                    Need client access?
                    <a href="/client-zone/register" class="ml-1 font-medium text-blue-600 hover:text-blue-500">
                        Register
                    </a>
                </p>
            </div>
        </Form>
    </AuthBase>
</template>
