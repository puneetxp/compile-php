import { Directive, InjectionToken, OnDestroy } from '@angular/core';
import { ICheckoutInstance, ICheckoutOptions, Window } from './types';
@Directive()
export declare class CheckoutService implements OnDestroy {
  private readonly window;
  private readonly document;
  private config;
  private openInPopup;
  private isScriptLoaded;
  private isScriptLoading;
  private receivedCheckoutJsInstance;
  readonly checkoutJsContainerId: string;
  private readonly checkoutJsInstanceSource$;
  readonly checkoutJsInstance$: import("rxjs").Observable<ICheckoutInstance>;
  constructor(window: Window, document: any);
  init(config: any, options?: ICheckoutOptions): void;
  private loadCheckoutScript;
  private setupCheckoutJs;
  private initializeCheckout;
  private getCheckoutJsObj;
  ngOnDestroy(): void;
}
export declare const WINDOW_TOKEN: InjectionToken<Window>;
