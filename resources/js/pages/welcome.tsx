import { Head, Link, usePage } from '@inertiajs/react';
import { KeyRound, ScanLine, ShieldCheck, Wallet } from 'lucide-react';
import { login, register } from '@/routes';

export default function Welcome({ canRegister = true }: { canRegister?: boolean }) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Web Wallet Spike" />
            <div className="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:justify-center lg:p-8 dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <header className="mb-6 w-full max-w-2xl text-sm">
                    <nav className="flex items-center justify-end gap-4">
                        {auth.user ? (
                            <Link
                                href="/wallet"
                                className="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal hover:border-[#1915014a] dark:border-[#3E3E3A] dark:hover:border-[#62605b]"
                            >
                                Go to Wallet
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="inline-block rounded-sm border border-transparent px-5 py-1.5 text-sm leading-normal hover:border-[#19140035] dark:hover:border-[#3E3E3A]"
                                >
                                    Log in
                                </Link>
                                {canRegister && (
                                    <Link
                                        href={register()}
                                        className="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal hover:border-[#1915014a] dark:border-[#3E3E3A] dark:hover:border-[#62605b]"
                                    >
                                        Register
                                    </Link>
                                )}
                            </>
                        )}
                    </nav>
                </header>

                <main className="w-full max-w-2xl">
                    <div className="mb-10 text-center">
                        <div className="mb-4 flex justify-center">
                            <Wallet className="h-12 w-12 text-[#1b1b18] dark:text-[#EDEDEC]" />
                        </div>
                        <h1 className="mb-3 text-3xl font-semibold tracking-tight">Web Wallet Spike</h1>
                        <p className="mx-auto max-w-lg text-base text-[#706f6c] dark:text-[#A1A09A]">
                            A spike implementation of a web-based identity wallet supporting the OpenID for Verifiable Credentials ecosystem.
                        </p>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="rounded-lg border border-[#e3e3e0] p-5 dark:border-[#3E3E3A]">
                            <ScanLine className="mb-3 h-6 w-6 text-[#706f6c] dark:text-[#A1A09A]" />
                            <h2 className="mb-1 text-sm font-semibold">Receive Credentials (OID4VCI)</h2>
                            <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                                Accept verifiable credentials from issuers using the OpenID for Verifiable Credential Issuance protocol with pre-authorized code flow.
                            </p>
                        </div>

                        <div className="rounded-lg border border-[#e3e3e0] p-5 dark:border-[#3E3E3A]">
                            <ShieldCheck className="mb-3 h-6 w-6 text-[#706f6c] dark:text-[#A1A09A]" />
                            <h2 className="mb-1 text-sm font-semibold">Present Credentials (OID4VP)</h2>
                            <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                                Present credentials to verifiers using OpenID for Verifiable Presentations with direct_post response mode.
                            </p>
                        </div>

                        <div className="rounded-lg border border-[#e3e3e0] p-5 dark:border-[#3E3E3A]">
                            <KeyRound className="mb-3 h-6 w-6 text-[#706f6c] dark:text-[#A1A09A]" />
                            <h2 className="mb-1 text-sm font-semibold">Selective Disclosure</h2>
                            <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                                Supports SD-JWT-VC credentials with selective disclosure and key binding, as well as plain W3C JWT-VC credentials.
                            </p>
                        </div>

                        <div className="rounded-lg border border-[#e3e3e0] p-5 dark:border-[#3E3E3A]">
                            <Wallet className="mb-3 h-6 w-6 text-[#706f6c] dark:text-[#A1A09A]" />
                            <h2 className="mb-1 text-sm font-semibold">Credential Management</h2>
                            <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                                Store, view, and manage multiple credentials with per-user EC P-256 key pairs for holder binding.
                            </p>
                        </div>
                    </div>

                    <p className="mt-8 text-center text-xs text-[#706f6c] dark:text-[#A1A09A]">
                        Built with Laravel 12, React, Inertia.js, and lcobucci/jwt.
                    </p>
                </main>
            </div>
        </>
    );
}
