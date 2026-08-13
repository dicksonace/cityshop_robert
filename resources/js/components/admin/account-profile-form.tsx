import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Props {
    updateUrl: string;
    name: string;
    email: string;
    mobile?: string | null;
}

export default function AccountProfileForm({ updateUrl, name, email, mobile }: Props) {
    const form = useForm({
        name: name ?? '',
        email: email ?? '',
        mobile: mobile ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(updateUrl, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="mt-4 space-y-4">
            <div className="grid gap-1.5">
                <Label htmlFor="account-name">Name</Label>
                <Input
                    id="account-name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    required
                />
                <InputError message={form.errors.name} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="account-email">Email</Label>
                <Input
                    id="account-email"
                    type="email"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    required
                />
                <InputError message={form.errors.email} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="account-mobile">Phone number</Label>
                <Input
                    id="account-mobile"
                    type="tel"
                    value={form.data.mobile}
                    onChange={(e) => form.setData('mobile', e.target.value)}
                    required
                />
                <InputError message={form.errors.mobile} />
            </div>
            <Button type="submit" disabled={form.processing}>
                Save details
            </Button>
        </form>
    );
}
