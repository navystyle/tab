import {BrowserModule} from '@angular/platform-browser';
import {NgModule} from '@angular/core';

import {AppComponent} from './app.component';
import {BrowserAnimationsModule} from '@angular/platform-browser/animations';
import {AppRoutingModule} from './app-routing.module';
import {SharedModule} from "./shared/shared.module";
import {CoreModule} from "./core/core.module";
import {LayoutModule} from "./layout/layout.module";
import {PageNotFoundComponent} from "./page-not-found/page-not-found.component";

@NgModule({
    declarations: [
        AppComponent,
        PageNotFoundComponent,
    ],
    imports: [
        BrowserModule,
        BrowserAnimationsModule,
        AppRoutingModule,
        SharedModule,
        CoreModule,
        LayoutModule,
    ],
    providers: [],
    bootstrap: [AppComponent]
})
export class AppModule {
}