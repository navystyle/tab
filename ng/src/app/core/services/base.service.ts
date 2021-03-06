import {Injectable} from '@angular/core';
import {HttpClient, HttpParams} from "@angular/common/http";
import {Observable, throwError} from "rxjs/index";
import {catchError} from "rxjs/internal/operators";
import {environment} from "../../../environments/environment";

@Injectable()
export class BaseService {
    constructor(private http: HttpClient) {
    }

    private formatErrors(error: any) {
        return throwError(error);
    }

    get(path: string, params: HttpParams = new HttpParams()): Observable<any> {
        return this.http.get(`${environment.apiUrl}${path}`, { params })
            .pipe(catchError(this.formatErrors));
    }

    put(path: string, body: Object = {}): Observable<any> {
        return this.http.put(
            `${environment.apiUrl}${path}`,
            JSON.stringify(body)
        ).pipe(catchError(this.formatErrors));
    }

    post(path: string, body: Object = {}): Observable<any> {
        return this.http.post(
            `${environment.apiUrl}${path}`,
            JSON.stringify(body)
        ).pipe(catchError(this.formatErrors));
    }

    delete(path): Observable<any> {
        return this.http.delete(
            `${environment.apiUrl}${path}`,
        ).pipe(catchError(this.formatErrors));
    }
}
