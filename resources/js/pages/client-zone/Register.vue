<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

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
        <form class="space-y-4" @submit.prevent="submit">
            <div class="space-y-1">
                <Label for="name">Full name</Label>
                <Input id="name" v-model="form.name" required />
                <InputError :message="form.errors.name" />
            </div>

            <div class="space-y-1">
                <Label for="email">Email</Label>
                <Input id="email" v-model="form.email" type="email" required />
                <InputError :message="form.errors.email" />
            </div>

            <div class="space-y-1">
                <Label for="password">Password</Label>
                <Input id="password" v-model="form.password" type="password" required />
                <InputError :message="form.errors.password" />
            </div>

            <div class="space-y-1">
                <Label for="password_confirmation">Confirm password</Label>
                <Input id="password_confirmation" v-model="form.password_confirmation" type="password" required />
            </div>

            <Button type="submit" :disabled="form.processing" class="w-full">
                {{ form.processing ? 'Submitting...' : 'Register' }}
            </Button>

            <p class="text-center text-sm text-gray-600">
                Already have access?
                <a href="/client-login" class="font-medium text-blue-600 hover:text-blue-500">Sign in</a>
            </p>
        </form>
    </AuthBase>
</template>
