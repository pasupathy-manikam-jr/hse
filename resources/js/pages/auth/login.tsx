import { Form, Head } from '@inertiajs/react';
import { useRef } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/passkey-verify';

type DemoLogin = { name: string; email: string; password: string };

type Props = {
    status?: string;
    canResetPassword: boolean;
    demoLogins: DemoLogin[];
};

export default function Login({ status, canResetPassword, demoLogins }: Props) {
    const emailRef = useRef<HTMLInputElement>(null);
    const passwordRef = useRef<HTMLInputElement>(null);

    // The form is uncontrolled, so fill the fields directly.
    const fill = (email: string) => {
        const login = demoLogins.find((l) => l.email === email);

        if (login && emailRef.current && passwordRef.current) {
            emailRef.current.value = login.email;
            passwordRef.current.value = login.password;
        }
    };

    return (
        <>
            <Head title="Log in" />

            <PasskeyVerify />

            <Form
                noValidate
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            {demoLogins.length > 0 && (
                                <div className="grid gap-3 rounded-lg border bg-muted/50 p-4">
                                    <Label id="quick-login">Quick login</Label>
                                    <RadioGroup
                                        aria-labelledby="quick-login"
                                        onValueChange={fill}
                                    >
                                        {demoLogins.map((login) => (
                                            <Label
                                                key={login.email}
                                                className="flex cursor-pointer items-center gap-3 font-normal"
                                            >
                                                <RadioGroupItem
                                                    value={login.email}
                                                />
                                                <span className="font-medium">
                                                    {login.name}
                                                </span>
                                                <span className="truncate text-muted-foreground">
                                                    {login.email}
                                                </span>
                                            </Label>
                                        ))}
                                    </RadioGroup>
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    ref={emailRef}
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">Password</Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm"
                                            tabIndex={5}
                                        >
                                            Forgot your password?
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    ref={passwordRef}
                                    id="password"
                                    name="password"
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">Remember me</Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Log in
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Enter your email and password below to log in',
};
