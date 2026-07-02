import type { RequestHandler } from "@builder.io/qwik-city";
import { buildRobotsTxt } from "~/lib/seo-files";

export const onGet: RequestHandler = ({ url, headers, send }) => {
  headers.set("Content-Type", "text/plain; charset=utf-8");
  send(200, buildRobotsTxt(url.origin));
};
