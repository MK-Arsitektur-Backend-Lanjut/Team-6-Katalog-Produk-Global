import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';

const errorEOF = new Counter('error_eof');
const errorReset = new Counter('error_connection_reset');
const errorTimeout = new Counter('error_timeout');
const error502 = new Counter('error_502');
const error504 = new Counter('error_504');
const errorOther = new Counter('error_other');

export const options = {
    scenarios: {
        load_test: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 5000 },
                { duration: '30s', target: 10000 },
                { duration: '5m', target: 10000 },
                { duration: '30s', target: 0 },
            ],
            gracefulRampDown: '30s',
        },
    },
    discardResponseBodies: true,
};

const BASE_URL = 'http://localhost:8000/api/v1/catalog/products';

export default function () {
    const params = {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        timeout: '300s',
    };

    const res = http.get(`${BASE_URL}?per_page=15`, params);

    if (res.status !== 200) {
        if (res.status === 0) {
            const errStr = String(res.error || '');
            if (errStr.includes('EOF')) {
                errorEOF.add(1);
            } else if (errStr.includes('reset')) {
                errorReset.add(1);
            } else if (errStr.includes('timeout') || errStr.includes('Timeout')) {
                errorTimeout.add(1);
            } else {
                errorOther.add(1);
            }
        } else if (res.status === 502) {
            error502.add(1);
        } else if (res.status === 504) {
            error504.add(1);
        } else {
            errorOther.add(1);
        }
    }

    check(res, {
        'status is 200': (r) => r.status === 200,
    });

    sleep(5);
}
