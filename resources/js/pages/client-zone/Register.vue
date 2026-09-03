<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { authInputClass, authLabelClass, authLinkClass } from '@/lib/authStyles';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/client-zone/register');
};
</script>

<template>
    <Head title="Client Registration" />
    <AuthBase title="Client Zone Registration" description="Register with your customer email to request access.">
        <form class="space-y-6" @submit.prevent="submit">
            <div class="space-y-5">
                <div class="space-y-2">
                    <Label for="name" :class="authLabelClass">Full name</Label>
                    <Input id="name" v-model="form.name" required :class="authInputClass" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="space-y-2">
                    <Label for="email" :class="authLabelClass">Email</Label>
                    <Input id="email" v-model="form.email" type="email" required :class="authInputClass" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="space-y-2">
                    <Label for="password" :class="authLabelClass">Password</Label>
                    <Input id="password" v-model="form.password" type="password" required :class="authInputClass" />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="space-y-2">
                    <Label for="password_confirmation" :class="authLabelClass">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        required
                        :class="authInputClass"
                    />
                </div>

                <Button type="submit" variant="auth" size="auth" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="mr-2 h-5 w-5 animate-spin" />
                    {{ form.processing ? 'Submitting...' : 'Register' }}
                </Button>
            </div>

            <p class="text-center text-sm text-gray-600">
                Already have access?
                <a href="/client-login" :class="authLinkClass">Sign in</a>
            </p>
        </form>
    </AuthBase>
</template>
