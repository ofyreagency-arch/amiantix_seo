"use client";

import { useActionState } from "react";
import { updateProfileAction } from "@/app/(app)/settings/actions";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

type ProfileSettingsFormProps = {
  name: string;
  email: string;
};

export function ProfileSettingsForm({ name, email }: ProfileSettingsFormProps) {
  const [state, formAction, pending] = useActionState(updateProfileAction, {});

  return (
    <form action={formAction} className="space-y-4">
      <Input name="name" label="Nom" defaultValue={name} required />
      <Input name="email" label="Email" type="email" defaultValue={email} required />

      {state.error && (
        <p className="rounded-lg bg-danger-subtle px-3 py-2 text-sm text-danger">
          {state.error}
        </p>
      )}

      {state.success && (
        <p className="rounded-lg bg-success-subtle px-3 py-2 text-sm text-success">
          {state.success}
        </p>
      )}

      <Button type="submit" loading={pending}>
        Enregistrer le profil
      </Button>
    </form>
  );
}
