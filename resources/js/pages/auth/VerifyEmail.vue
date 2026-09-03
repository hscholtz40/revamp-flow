<script setup lang="ts">
import EmailVerificationNotificationController from '@/actions/App/Http/Controllers/Auth/EmailVerificationNotificationController';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { authLinkClass } from '@/lib/authStyles';
import { logout } from '@/routes';
import { Form, Head } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout
        title="Verify email"
        description="Please verify your email address by clicking on the link we just emailed to you."
    >
        <Head title="Email verification" />

        <div
            v-if="status === 'verification-link-sent'"
            class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-center text-sm font-medium text-green-700"
        >
            A new verification link has been sent to the email address you
            provided during registration.
        </div>

        <Form
            v-bind="EmailVerificationNotificationController.store.form()"
            class="space-y-6 text-center"
            v-slot="{ processing }"
        >
            <Button type="submit" variant="auth" size="auth" :disabled="processing">
                <LoaderCircle v-if="processing" class="mr-2 h-5 w-5 animate-spin" />
                {{ processing ? 'Sending...' : 'Resend verification email' }}
            </Button>

            <TextLink
                :href="logout()"
                as="button"
                :class="['mx-auto block text-sm', authLinkClass]"
            >
                Log out
            </TextLink>
        </Form>
    </AuthLayout>
</template>
