import { cn } from '@/lib/utils';
import { PropsWithChildren } from 'react';

export function H1({ children, className }: PropsWithChildren<{ className?: string }>) {
  return <h1 className={cn('ds-heading-1', className)}>{children}</h1>;
}

export function Subtext({ children, className }: PropsWithChildren<{ className?: string }>) {
  return <p className={cn('ds-subtext', className)}>{children}</p>;
}

export function Label({ children, className }: PropsWithChildren<{ className?: string }>) {
  return <p className={cn('ds-label', className)}>{children}</p>;
}

export function NumberLg({ children, className }: PropsWithChildren<{ className?: string }>) {
  return <p className={cn('ds-number-lg', className)}>{children}</p>;
}
