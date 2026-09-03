<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { authInputClass, authLabelClass } from '@/lib/authStyles';
import { store } from '@/routes/password/confirm';
import { Form, Head } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
</script>

<template>
    <AuthLayout
        title="Confirm your password"
        description="This is a secure area of the application. Please confirm your password before continuing."
    >
        <Head title="Confirm password" />

        <Form
            v-bind="store.form()"
            reset-on-success
            v-slot="{ errors, processing }"
            class="space-y-6"
        >
            <div class="space-y-5">
                <div class="space-y-2">
                    <Label for="password" :class="authLabelClass">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        :class="authInputClass"
                        required
                        autocomplete="current-password"
                        autofocus
                    />

                    <InputError :message="errors.password" />
                </div>

                <Button
                    type="submit"
                    variant="auth"
                    size="auth"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <LoaderCircle
                        v-if="processing"
                        class="mr-2 h-5 w-5 animate-spin"
                    />
                    {{ processing ? 'Confirming...' : 'Confirm Password' }}
                </Button>
            </div>
        </Form>
    </AuthLayout>
</template>
